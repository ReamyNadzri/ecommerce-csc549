import pandas as pd
import numpy as np
from flask import Flask, request, jsonify
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from implicit.als import AlternatingLeastSquares
from scipy.sparse import csr_matrix
import sqlalchemy  # Import the new library
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
        
        # Initialize attributes that will be created later
        self.products_df = pd.DataFrame()
        self.users_df = pd.DataFrame()
        self.purchases_df = pd.DataFrame()
        self.views_df = pd.DataFrame()
        self.als_model = None
        self.user_item_matrix = None
        self.user_item_sparse = None
        self.content_similarity_matrix = None
        self.weights = {'view': 1.0, 'purchase': 5.0}

        # Load data and train models if the database connection is successful
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
        """Load all data from the database and process it correctly."""
        logger.info("Loading all data from database...")
        try:
            # Load raw data from the database
            self.products_df = pd.read_sql_query("SELECT * FROM products WHERE IsActive = 1", self.db_engine)
            self.users_df = pd.read_sql_query("SELECT * FROM users", self.db_engine)
            """ self.purchases_df = pd.read_sql_query("SELECT o.UserID, oi.ProductID, oi.Quantity FROM orders o JOIN order_items oi ON o.OrderID = oi.OrderID WHERE o.OrderStatus = 'Paid'", self.db_engine) """
            self.purchases_df = pd.read_sql_query("SELECT o.UserID, o.OrderDate, oi.ProductID, oi.Quantity FROM orders o JOIN order_items oi ON o.OrderID = oi.OrderID WHERE o.OrderStatus = 'Paid'", self.db_engine)
            self.views_df = pd.read_sql_query("SELECT UserID, ProductID, ViewTimestamp FROM product_views", self.db_engine)
            
            logger.info(f"Loaded {len(self.purchases_df)} purchase records and {len(self.views_df)} view records from DB.")

            # --- CORRECTED DATA CONVERSION ---
            # Convert ID columns to numeric types for all dataframes
            for df in [self.products_df, self.users_df, self.purchases_df, self.views_df]:
                for col in ['ProductID', 'UserID']:
                    if col in df.columns:
                        # Convert to number, forcing errors into NaN (Not a Number)
                        df[col] = pd.to_numeric(df[col], errors='coerce')
            
            # Drop rows where the key IDs are NaN (meaning they couldn't be converted)
            self.products_df.dropna(subset=['ProductID'], inplace=True)
            self.users_df.dropna(subset=['UserID'], inplace=True)
            self.purchases_df.dropna(subset=['UserID', 'ProductID'], inplace=True)
            self.views_df.dropna(subset=['UserID', 'ProductID'], inplace=True)

            # Ensure IDs are integers for matrix operations
            for df in [self.products_df, self.users_df, self.purchases_df, self.views_df]:
                for col in ['ProductID', 'UserID']:
                    if col in df.columns:
                        df[col] = df[col].astype(int)

            logger.info(f"After cleaning, there are {len(self.purchases_df)} valid purchase records.")
            
        except Exception as e:
            logger.error(f"FATAL: Error during data loading. Error: {e}")

    def create_interaction_matrix(self):
        """Create user-item interaction matrix with weighted implicit feedback"""
        try:
            interactions = []
            
            if not self.purchases_df.empty:
                purchase_interactions = self.purchases_df.groupby(['UserID', 'ProductID']).agg({'Quantity': 'sum'}).reset_index()
                purchase_interactions['score'] = purchase_interactions['Quantity'] * self.weights['purchase']
                interactions.append(purchase_interactions[['UserID', 'ProductID', 'score']])
            
            if not self.views_df.empty:
                view_interactions = self.views_df.groupby(['UserID', 'ProductID']).size().reset_index(name='view_count')
                view_interactions['score'] = view_interactions['view_count'] * self.weights['view']
                interactions.append(view_interactions[['UserID', 'ProductID', 'score']])
            
            if interactions:
                all_interactions = pd.concat(interactions, ignore_index=True)
                combined_interactions = all_interactions.groupby(['UserID', 'ProductID'])['score'].sum().reset_index()
                
                self.user_item_matrix = combined_interactions.pivot(index='UserID', columns='ProductID', values='score').fillna(0)
                self.user_item_sparse = csr_matrix(self.user_item_matrix.values)
                
                logger.info(f"User-item matrix created: {self.user_item_matrix.shape}")
            else:
                logger.warning("No user interactions found in the database to build a model.")
                self.user_item_matrix = pd.DataFrame()
                
        except Exception as e:
            logger.error(f"Error creating interaction matrix: {str(e)}")
            self.user_item_matrix = pd.DataFrame()
    
    def train_als_model(self):
        """Train ALS (Alternating Least Squares) model"""
        try:
            if self.user_item_sparse is None or self.user_item_sparse.shape[0] == 0:
                logger.warning("No interaction data available for training ALS model.")
                return
            
            self.als_model = AlternatingLeastSquares(factors=64, regularization=0.1, iterations=50, alpha=40, random_state=42, use_gpu=False)
            logger.info("Training ALS model...")
            self.als_model.fit(self.user_item_sparse)
            logger.info("ALS model trained successfully")
            
        except Exception as e:
            logger.error(f"Error training ALS model: {str(e)}")
            self.als_model = None
    
    def create_content_features(self):
        """Create content-based features for cold start and hybrid recommendations"""
        try:
            if self.products_df.empty:
                logger.warning("No product data available to create content features.")
                return
            
            self.products_df['combined_features'] = (
                self.products_df['BrandName'].fillna('') + ' ' + self.products_df['Flavour'].fillna('') + ' ' +
                self.products_df['ProductType'].fillna('') + ' ' + self.products_df['Description'].fillna('')
            )
            
            tfidf = TfidfVectorizer(max_features=500, stop_words='english', ngram_range=(1, 2), min_df=1)
            self.product_features = tfidf.fit_transform(self.products_df['combined_features'])
            self.content_similarity_matrix = cosine_similarity(self.product_features)
            logger.info(f"Content features created: {self.product_features.shape}")
            
        except Exception as e:
            logger.error(f"Error creating content features: {str(e)}")
            self.content_similarity_matrix = None
    
    def get_als_recommendations(self, user_id, n_recommendations=10):
        """Get recommendations using ALS collaborative filtering"""
        try:
            if (self.als_model is None or self.user_item_matrix.empty or user_id not in self.user_item_matrix.index):
                return []
            
            user_idx = self.user_item_matrix.index.get_loc(user_id)
            recommendations = self.als_model.recommend(user_idx, self.user_item_sparse[user_idx], N=n_recommendations, filter_already_liked_items=True)
            
            # The corrected code using zip()
            item_ids, confidence_scores = recommendations
            return [(self.user_item_matrix.columns[item_id], float(score)) for item_id, score in zip(item_ids, confidence_scores)]
            
        except Exception as e:
            logger.error(f"Error getting ALS recommendations: {str(e)}")
            return []
    
    def get_content_recommendations(self, product_id, n_recommendations=10):
        """Get content-based recommendations for similar products"""
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
        """Get popular items based on purchase frequency and recency"""
        try:
            if self.purchases_df.empty:
                logger.warning("No purchase data for popularity. Falling back to random items.")
                if self.products_df.empty: return []
                popular_products = self.products_df.sample(min(n_recommendations, len(self.products_df)))
                return [(row['ProductID'], 1.0) for _, row in popular_products.iterrows()]
            
            now = datetime.datetime.now()
            popularity_scores = {}
            for _, purchase in self.purchases_df.iterrows():
                recency_weight = max(0.1, 1.0 - ((now - pd.to_datetime(purchase['OrderDate'])).days / 90.0))
                score = purchase['Quantity'] * recency_weight
                popularity_scores[purchase['ProductID']] = popularity_scores.get(purchase['ProductID'], 0) + score
            
            sorted_products = sorted(popularity_scores.items(), key=lambda x: x[1], reverse=True)
            return sorted_products[:n_recommendations]
            
        except Exception as e:
            logger.error(f"Error getting popular recommendations: {str(e)}")
            return []
    
    def get_hybrid_recommendations(self, user_id, n_recommendations=10):
        """Get hybrid recommendations combining ALS and content-based approaches"""
        try:
            recommendations = {}
            
            als_recs = self.get_als_recommendations(user_id, n_recommendations)
            for pid, score in als_recs: recommendations[pid] = recommendations.get(pid, 0) + score * 0.7
            
            if not self.purchases_df.empty:
                user_purchases = self.purchases_df[self.purchases_df['UserID'] == user_id]['ProductID'].tolist()
                for pid in user_purchases[-3:]:
                    content_recs = self.get_content_recommendations(pid, 5)
                    for rec_pid, sim in content_recs: recommendations[rec_pid] = recommendations.get(rec_pid, 0) + sim * 0.2
            
            popular_recs = self.get_popular_recommendations(n_recommendations)
            for pid, pop in popular_recs: recommendations[pid] = recommendations.get(pid, 0) + pop * 0.1
            
            sorted_recs = sorted(recommendations.items(), key=lambda x: x[1], reverse=True)
            
            if len(sorted_recs) < n_recommendations:
                existing_ids = {pid for pid, _ in sorted_recs}
                missing_popular = [item for item in popular_recs if item[0] not in existing_ids]
                sorted_recs.extend(missing_popular)

            return sorted_recs[:n_recommendations]
            
        except Exception as e:
            logger.error(f"Error getting hybrid recommendations: {str(e)}")
            return self.get_popular_recommendations(n_recommendations)
    
    def get_cold_start_recommendations(self, user_id, n_recommendations=10):
        """Handle cold start problem for new users by serving popular items."""
        logger.info(f"Cold start for user {user_id}. Serving popular recommendations.")
        return self.get_popular_recommendations(n_recommendations)
    
    def get_product_details(self, product_recommendations):
        """Get detailed product information with scores. This function is now safe."""
        try:
            if not product_recommendations or self.products_df.empty:
                return []
            
            # --- FIX #2: SAFE, READ-ONLY LOGIC ---
            # We know ProductID in products_df is an integer from load_data()
            product_ids = [pid for pid, _ in product_recommendations]
            scores = {pid: score for pid, score in product_recommendations}
            
            # Find the matching products without changing the original dataframe
            products = self.products_df[self.products_df['ProductID'].isin(product_ids)].copy()
            
            # Add recommendation scores and maintain order
            products['recommendation_score'] = products['ProductID'].map(scores)
            products = products.sort_values(by='recommendation_score', ascending=False)
            
            return products.to_dict('records')
            
        except Exception as e:
            logger.error(f"Error getting product details: {str(e)}")
            return []

# Initialize the recommendation system
rec_system = FoodRecommendationSystem()

# Flask API Routes
@app.route('/api/recommend/<user_id>')
def recommend_for_user(user_id):
    """Get recommendations for a specific user"""
    try:
        user_id_int = int(user_id)
        n_recommendations = request.args.get('n', 10, type=int)
        
        # We now check if the matrix is not None before trying to use it.
        user_exists = (rec_system.user_item_matrix is not None and
                       not rec_system.user_item_matrix.empty and
                       user_id_int in rec_system.user_item_matrix.index)
        
        if user_exists:
            recommendations = rec_system.get_hybrid_recommendations(user_id_int, n_recommendations)
            rec_type = 'hybrid'
        else:
            # This part will now be reached correctly
            recommendations = rec_system.get_cold_start_recommendations(user_id_int, n_recommendations)
            rec_type = 'cold_start (popular)'
        
        products = rec_system.get_product_details(recommendations)
        
        return jsonify({
            'success': True,
            'user_id': user_id,
            'user_exists_in_model': user_exists,
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
