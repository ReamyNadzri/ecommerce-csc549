import pandas as pd
import numpy as np
from flask import Flask, request, jsonify
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from implicit.als import AlternatingLeastSquares
from scipy.sparse import csr_matrix
import sqlalchemy
import warnings
import logging
import datetime

warnings.filterwarnings('ignore')
app = Flask(__name__)
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class FoodRecommendationSystem:
    def __init__(self):
        logger.info("Initializing Food Recommendation System...")
        self.db_engine = self.create_db_engine()
        
        # Initialize attributes
        self.products_df = pd.DataFrame()
        self.users_df = pd.DataFrame()
        self.purchases_df = pd.DataFrame()
        self.views_df = pd.DataFrame()
        
        ### NEW: Dictionaries to map string UserIDs to integer indices for the model ###
        self.user_id_to_idx = {}
        self.idx_to_user_id = {}

        self.als_model = None
        self.user_item_matrix = None
        self.user_item_sparse = None
        self.content_similarity_matrix = None
        self.weights = {'view': 1.0, 'purchase': 5.0}

        # Load data and train models
        if self.db_engine:
            self.load_all_data()
            if not self.products_df.empty:
                logger.info("Processing data and training models...")
                self.create_interaction_matrix()
                self.train_als_model()
                self.create_content_features()
                logger.info("Model training and feature creation complete.")
            else:
                logger.warning("Product data is empty, skipping model training.")

    def create_db_engine(self):
        """Creates a SQLAlchemy database engine."""
        try:
            connection_string = f"mysql+pymysql://haziq:54321@10.62.52.33:3307/ecommercedb"
            engine = sqlalchemy.create_engine(connection_string)
            engine.connect()
            logger.info("SQLAlchemy database engine created successfully.")
            return engine
        except Exception as e:
            logger.error(f"FATAL: Could not create database engine. Error: {e}")
            return None

    def load_all_data(self):
        """Load all data from the database."""
        logger.info("Loading all data from database...")
        try:
            self.products_df = pd.read_sql_query("SELECT * FROM products WHERE IsActive = 1", self.db_engine)
            self.users_df = pd.read_sql_query("SELECT * FROM users", self.db_engine)
            self.purchases_df = pd.read_sql_query("SELECT o.UserID, o.OrderDate, oi.ProductID, oi.Quantity FROM orders o JOIN order_items oi ON o.OrderID = oi.OrderID WHERE o.OrderStatus = 'Paid'", self.db_engine)
            self.views_df = pd.read_sql_query("SELECT UserID, ProductID, ViewTimestamp FROM product_views", self.db_engine)
            
            logger.info(f"Loaded {len(self.purchases_df)} purchase records and {len(self.views_df)} view records from DB.")

            ### MODIFIED: Only clean and convert ProductID, leaving UserID as a string ###
            for df in [self.products_df, self.purchases_df, self.views_df]:
                if 'ProductID' in df.columns:
                    df['ProductID'] = pd.to_numeric(df['ProductID'], errors='coerce')
                    df.dropna(subset=['ProductID'], inplace=True)
                    df['ProductID'] = df['ProductID'].astype(int)

        except Exception as e:
            logger.error(f"FATAL: Error during data loading. Error: {e}")

    def create_interaction_matrix(self):
        """Create user-item interaction matrix and UserID mappings."""
        try:
            interactions = []
            if not self.purchases_df.empty:
                interactions.append(self.purchases_df.rename(columns={'Quantity': 'score'})[['UserID', 'ProductID', 'score']].assign(score=lambda x: x['score'] * self.weights['purchase']))
            if not self.views_df.empty:
                view_interactions = self.views_df.groupby(['UserID', 'ProductID']).size().reset_index(name='score')
                interactions.append(view_interactions.assign(score=lambda x: x['score'] * self.weights['view']))
            
            if interactions:
                all_interactions = pd.concat(interactions, ignore_index=True)
                combined_interactions = all_interactions.groupby(['UserID', 'ProductID'])['score'].sum().reset_index()
                
                ### NEW: Create the mapping from string UserID to integer index ###
                unique_user_ids = combined_interactions['UserID'].unique()
                self.user_id_to_idx = {user_id: i for i, user_id in enumerate(unique_user_ids)}
                self.idx_to_user_id = {i: user_id for user_id, i in self.user_id_to_idx.items()}
                
                # Map string UserIDs to their new integer index
                combined_interactions['user_idx'] = combined_interactions['UserID'].map(self.user_id_to_idx)

                ### MODIFIED: Pivot using the new integer 'user_idx' ###
                self.user_item_matrix = combined_interactions.pivot(index='user_idx', columns='ProductID', values='score').fillna(0)
                self.user_item_sparse = csr_matrix(self.user_item_matrix.values)
                
                logger.info(f"User-item matrix created: {self.user_item_matrix.shape} with {len(self.user_id_to_idx)} unique users.")
            else:
                logger.warning("No user interactions found to build a model.")
                self.user_item_matrix = pd.DataFrame()
                
        except Exception as e:
            logger.error(f"Error creating interaction matrix: {str(e)}")
            self.user_item_matrix = pd.DataFrame()
    
    def train_als_model(self):
        """Train ALS model."""
        try:
            if self.user_item_sparse is None or self.user_item_sparse.shape[0] == 0:
                logger.warning("No interaction data for training ALS model.")
                return
            
            self.als_model = AlternatingLeastSquares(factors=64, regularization=0.1, iterations=50, alpha=40, random_state=42, use_gpu=False)
            logger.info("Training ALS model...")
            self.als_model.fit(self.user_item_sparse)
            logger.info("ALS model trained successfully")
            
        except Exception as e:
            logger.error(f"Error training ALS model: {str(e)}")

    def create_content_features(self):
        """Create content-based features."""
        try:
            if self.products_df.empty:
                logger.warning("No product data for content features.")
                return
            
            ### MODIFIED: Convert all columns to string before joining ###
            self.products_df['combined_features'] = self.products_df.astype(str).fillna('').agg(' '.join, axis=1)
            
            tfidf = TfidfVectorizer(max_features=500, stop_words='english')
            self.product_features = tfidf.fit_transform(self.products_df['combined_features'])
            self.content_similarity_matrix = cosine_similarity(self.product_features)
            logger.info(f"Content features created: {self.product_features.shape}")
            
        except Exception as e:
            logger.error(f"Error creating content features: {str(e)}")

    ### MODIFIED: Function now accepts the integer user_idx ###
    def get_als_recommendations(self, user_idx, n_recommendations=10):
        """Get recommendations using ALS from a user's integer index."""
        try:
            if self.als_model is None: return []
            recommendations = self.als_model.recommend(user_idx, self.user_item_sparse[user_idx], N=n_recommendations, filter_already_liked_items=True)
            item_ids, scores = recommendations
            return [(self.user_item_matrix.columns[item_id], float(score)) for item_id, score in zip(item_ids, scores)]
        except Exception as e:
            logger.error(f"Error getting ALS recommendations: {str(e)}")
            return []
    
    def get_content_recommendations(self, product_id, n_recommendations=10):
        """Get content-based recommendations for similar products."""
        try:
            if self.content_similarity_matrix is None: return []
            product_idx_series = self.products_df[self.products_df['ProductID'] == product_id].index
            if product_idx_series.empty: return []
            
            product_idx = product_idx_series[0]
            similarity_scores = list(enumerate(self.content_similarity_matrix[product_idx]))
            similar_products = sorted(similarity_scores, key=lambda x: x[1], reverse=True)[1:n_recommendations+1]
            return [(self.products_df.iloc[i[0]]['ProductID'], i[1]) for i in similar_products]
        except Exception as e:
            logger.error(f"Error getting content recommendations: {str(e)}")
            return []

    def get_popular_recommendations(self, n_recommendations=10, category=None):
        """Get popular items based on purchase frequency and recency."""
        try:
            if self.purchases_df.empty: return []
            
            now = datetime.datetime.now()
            popularity_scores = {}
            for _, purchase in self.purchases_df.iterrows():
                recency_weight = max(0.1, 1.0 - ((now - pd.to_datetime(purchase['OrderDate'])).days / 90.0))
                score = purchase['Quantity'] * recency_weight
                popularity_scores[purchase['ProductID']] = popularity_scores.get(purchase['ProductID'], 0) + score
            
            return sorted(popularity_scores.items(), key=lambda x: x[1], reverse=True)[:n_recommendations]
        except Exception as e:
            logger.error(f"Error getting popular recommendations: {str(e)}")
            return []
    
    ### MODIFIED: Function now accepts user_idx but also looks up original string ID ###
    def get_hybrid_recommendations(self, user_idx, n_recommendations=10):
        """Get hybrid recommendations from a user's integer index."""
        try:
            recommendations = {}
            als_recs = self.get_als_recommendations(user_idx, n_recommendations)
            for pid, score in als_recs: recommendations[pid] = recommendations.get(pid, 0) + score * 0.7
            
            if not self.purchases_df.empty:
                # Get original string ID from the index to look up purchases
                string_user_id = self.idx_to_user_id.get(user_idx)
                if string_user_id:
                    user_purchases = self.purchases_df[self.purchases_df['UserID'] == string_user_id]['ProductID'].tolist()
                    for pid in user_purchases[-3:]:
                        content_recs = self.get_content_recommendations(pid, 5)
                        for rec_pid, sim in content_recs: recommendations[rec_pid] = recommendations.get(rec_pid, 0) + sim * 0.2
            
            popular_recs = self.get_popular_recommendations(n_recommendations)
            for pid, pop in popular_recs: recommendations[pid] = recommendations.get(pid, 0) + pop * 0.1
            
            return sorted(recommendations.items(), key=lambda x: x[1], reverse=True)[:n_recommendations]
        except Exception as e:
            logger.error(f"Error getting hybrid recommendations: {str(e)}")
            return self.get_popular_recommendations(n_recommendations)
    
    def get_cold_start_recommendations(self, n_recommendations=10):
        """Handle cold start by serving popular items."""
        return self.get_popular_recommendations(n_recommendations)
    
    def get_product_details(self, product_recommendations):
        """Get detailed product information with scores."""
        try:
            if not product_recommendations or self.products_df.empty: return []
            product_ids = [pid for pid, _ in product_recommendations]
            scores = {pid: score for pid, score in product_recommendations}
            products = self.products_df[self.products_df['ProductID'].isin(product_ids)].copy()
            products['recommendation_score'] = products['ProductID'].map(scores)
            return products.sort_values(by='recommendation_score', ascending=False).to_dict('records')
        except Exception as e:
            logger.error(f"Error getting product details: {str(e)}")
            return []

# Initialize the system
rec_system = FoodRecommendationSystem()

# --- API Routes ---
@app.route('/api/recommend/<user_id>')
def recommend_for_user(user_id):
    """Get recommendations for a specific user using their string ID."""
    try:
        ### MODIFIED: No longer converting user_id to int ###
        n_recommendations = request.args.get('n', 10, type=int)
        
        ### MODIFIED: Check if the string user_id exists in our mapping ###
        if user_id in rec_system.user_id_to_idx:
            # User exists, get their integer index for the model
            user_idx = rec_system.user_id_to_idx[user_id]
            recommendations = rec_system.get_hybrid_recommendations(user_idx, n_recommendations)
            rec_type = 'hybrid'
            user_exists_in_model = True
        else:
            # User is new or has no interactions (cold start)
            recommendations = rec_system.get_cold_start_recommendations(n_recommendations)
            rec_type = 'cold_start (popular)'
            user_exists_in_model = False
        
        products = rec_system.get_product_details(recommendations)
        
        return jsonify({
            'success': True,
            'user_id': user_id,
            'user_exists_in_model': user_exists_in_model,
            'recommendation_type': rec_type,
            'recommendations': products
        })
    except Exception as e:
        logger.error(f"Error in recommend_for_user: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/similar/<product_id>')
def get_similar_products(product_id):
    """Get products similar to a given product"""
    try:
        n_recommendations = request.args.get('n', 10, type=int)
        
        similar_products = rec_system.get_content_recommendations(product_id, n_recommendations)
        products = rec_system.get_product_details(similar_products)
        
        return jsonify({
            'success': True,
            'product_id': product_id,
            'similar_products': products
        })
        
    except Exception as e:
        logger.error(f"Error in get_similar_products: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/popular')
def get_popular_products():
    """Get popular products"""
    try:
        n_recommendations = request.args.get('n', 10, type=int)
        category = request.args.get('category', None)
        
        popular_products = rec_system.get_popular_recommendations(n_recommendations, category)
        products = rec_system.get_product_details(popular_products)
        
        return jsonify({
            'success': True,
            'popular_products': products,
            'category': category
        })
        
    except Exception as e:
        logger.error(f"Error in get_popular_products: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/log_interaction', methods=['POST'])
def log_user_interaction():
    """Log user interaction"""
    try:
        data = request.json
        user_id = data.get('user_id')
        product_id = data.get('product_id')
        interaction_type = data.get('type', 'view')
        metadata = data.get('metadata', {})
        
        rec_system.log_interaction(user_id, product_id, interaction_type, metadata)
        
        return jsonify({'success': True})
        
    except Exception as e:
        logger.error(f"Error in log_user_interaction: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/retrain', methods=['POST'])
def retrain_model():
    """Retrain the recommendation model"""
    try:
        success = rec_system.retrain_model()
        return jsonify({'success': success})
        
    except Exception as e:
        logger.error(f"Error in retrain_model: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/stats')
def get_system_stats():
    """Get system statistics"""
    try:
        stats = rec_system.get_model_stats()
        return jsonify(stats)
        
    except Exception as e:
        logger.error(f"Error in get_system_stats: {str(e)}")
        return jsonify({'error': str(e)}), 500

@app.route('/api/products')
def get_all_products():
    """Get all available products"""
    try:
        products = rec_system.products_df.to_dict('records')
        return jsonify({
            'success': True,
            'products': products
        })
        
    except Exception as e:
        logger.error(f"Error in get_all_products: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500
    
if __name__ == '__main__':
    logger.info("Initializing Food Recommendation System...")
    rec_system = FoodRecommendationSystem()
    
    if rec_system.db_engine is not None:
        logger.info("Starting Flask application server...")
        app.run(debug=True, host='0.0.0.0', port=5000)
    else:
        logger.error("Could not start Flask server because database engine failed to initialize.")
