import pandas as pd
import numpy as np
from flask import Flask, request, jsonify, render_template
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from implicit.als import AlternatingLeastSquares
import mysql.connector
import json
from datetime import datetime, timedelta
import logging
from functools import lru_cache
from scipy.sparse import csr_matrix
import warnings
warnings.filterwarnings('ignore')

app = Flask(__name__)
app.config['SECRET_KEY'] = 'a4ebc378534b0f4de20c7c412283607c1b8c721a6e48a4730af0b09d1f923fc7'  # Replace with your secret key

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class FoodRecommendationSystem:
    def __init__(self):
        self.db_config = {
            'host': 'localhost',
            'user': 'haziq',
            'password': '54321',
            'database': 'ecommercedb'
        }
        self.als_model = None
        self.user_item_matrix = None
        self.user_item_sparse = None
        self.product_features = None
        self.content_similarity_matrix = None
        
        # Interaction weights for implicit feedback
        self.weights = {
            'view': 1.0,
            'cart_add': 2.0,
            'purchase': 5.0,
            'time_spent': 0.1  # per second
        }
        
        # Initialize system
        self.load_data()
        self.create_interaction_matrix()
        self.train_als_model()
        self.create_content_features()
    
    def get_db_connection(self):
        """Get database connection"""
        conn = mysql.connector.connect(**self.db_config)
        return conn
    
    def load_data(self):
        """Load data from database"""
        try:
            conn = self.get_db_connection()
            
            # Load products (food items)
            self.products_df = pd.read_sql_query("""
                SELECT ProductID, BrandName, Flavour, SpicyLevel, ProductType, 
                       Description, PriceMYR, IsVegetarian, IngredientList,
                       AllergenInfo, KeyFeaturesNotes, Originality, 
                       StockQuantity, IsActive
                FROM products 
                WHERE IsActive = 1
            """, conn)
            
            # Load users
            self.users_df = pd.read_sql_query("""
                SELECT UserID, Username, Email, FirstName, LastName, CreatedAt
                FROM users
            """, conn)
            
            # Load purchase interactions (orders + order_items)
            self.purchases_df = pd.read_sql_query("""
                SELECT o.UserID, oi.ProductID, oi.Quantity, 
                       o.OrderDate, oi.PriceAtPurchase
                FROM orders o
                JOIN order_items oi ON o.OrderID = oi.OrderID
                WHERE o.OrderStatus = 'Paid'
            """, conn)
            
            # Load view interactions (updated for MySQL date function)
            self.views_df = pd.read_sql_query("""
                SELECT UserID, ProductID, ViewTimestamp
                FROM product_views
                WHERE ViewTimestamp >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            """, conn)
            
            conn.close()
            
            # Create sample data if tables are empty (for testing)
            if self.products_df.empty:
                self.create_sample_data()
            
            logger.info(f"Data loaded: {len(self.products_df)} products, {len(self.users_df)} users")
            
        except Exception as e:
            logger.error(f"Error loading data: {str(e)}")
            self.create_sample_data()
    
    def create_sample_data(self):
        """Create sample data for testing"""
        logger.info("Creating sample data for testing...")
        
        # Sample food products
        sample_products = []
        food_types = ['Instant Noodles', 'Rice Dish', 'Curry', 'Snacks', 'Beverages']
        spicy_levels = [0, 1, 2, 3, 4, 5]
        brands = ['Maggi', 'Indomie', 'Nissin', 'MyKuali', 'Mamee']
        
        for i in range(1, 51):  # 50 food items
            product = {
                'ProductID': f'FOOD{i:03d}',
                'BrandName': np.random.choice(brands),
                'Flavour': f'Flavor {i}',
                'SpicyLevel': np.random.choice(spicy_levels),
                'ProductType': np.random.choice(food_types),
                'Description': f'Delicious food item {i}',
                'PriceMYR': round(np.random.uniform(2.50, 25.00), 2),
                'IsVegetarian': np.random.choice([0, 1]),
                'IngredientList': 'Sample ingredients',
                'AllergenInfo': 'May contain nuts',
                'KeyFeaturesNotes': 'Quick preparation',
                'Originality': np.random.choice(['Local', 'International']),
                'StockQuantity': np.random.randint(10, 100),
                'IsActive': 1
            }
            sample_products.append(product)
        
        self.products_df = pd.DataFrame(sample_products)
        
        # Sample users
        sample_users = []
        for i in range(1, 5):  # 4 test users
            user = {
                'UserID': i,
                'Username': f'user{i}',
                'Email': f'user{i}@test.com',
                'FirstName': f'User',
                'LastName': f'{i}',
                'CreatedAt': datetime.now()
            }
            sample_users.append(user)
        
        self.users_df = pd.DataFrame(sample_users)
        
        # Sample interactions
        sample_purchases = []
        sample_views = []
        
        for user_id in range(1, 5):
            # Each user purchases 3-8 items
            n_purchases = np.random.randint(3, 9)
            purchased_items = np.random.choice(self.products_df['ProductID'], n_purchases, replace=False)
            
            for product_id in purchased_items:
                purchase = {
                    'UserID': user_id,
                    'ProductID': product_id,
                    'Quantity': np.random.randint(1, 4),
                    'OrderDate': datetime.now() - timedelta(days=np.random.randint(1, 60)),
                    'PriceAtPurchase': self.products_df[self.products_df['ProductID'] == product_id]['PriceMYR'].iloc[0]
                }
                sample_purchases.append(purchase)
            
            # Each user views 10-20 items
            n_views = np.random.randint(10, 21)
            viewed_items = np.random.choice(self.products_df['ProductID'], n_views, replace=True)
            
            for product_id in viewed_items:
                view = {
                    'UserID': user_id,
                    'ProductID': product_id,
                    'ViewTimestamp': datetime.now() - timedelta(days=np.random.randint(1, 30))
                }
                sample_views.append(view)
        
        self.purchases_df = pd.DataFrame(sample_purchases)
        self.views_df = pd.DataFrame(sample_views)
        
        logger.info("Sample data created successfully")
    
    def create_interaction_matrix(self):
        """Create user-item interaction matrix with weighted implicit feedback"""
        try:
            interactions = []
            
            # Process purchase interactions (highest weight)
            if not self.purchases_df.empty:
                purchase_interactions = self.purchases_df.groupby(['UserID', 'ProductID']).agg({
                    'Quantity': 'sum'
                }).reset_index()
                purchase_interactions['score'] = purchase_interactions['Quantity'] * self.weights['purchase']
                interactions.append(purchase_interactions[['UserID', 'ProductID', 'score']])
            
            # Process view interactions (lower weight)
            if not self.views_df.empty:
                view_interactions = self.views_df.groupby(['UserID', 'ProductID']).size().reset_index(name='view_count')
                view_interactions['score'] = view_interactions['view_count'] * self.weights['view']
                interactions.append(view_interactions[['UserID', 'ProductID', 'score']])
            
            if interactions:
                # Combine all interactions
                all_interactions = pd.concat(interactions, ignore_index=True)
                combined_interactions = all_interactions.groupby(['UserID', 'ProductID'])['score'].sum().reset_index()
                
                # Create user-item matrix
                self.user_item_matrix = combined_interactions.pivot(
                    index='UserID', 
                    columns='ProductID', 
                    values='score'
                ).fillna(0)
                
                # Convert to sparse matrix for ALS
                self.user_item_sparse = csr_matrix(self.user_item_matrix.values)
                
                logger.info(f"User-item matrix created: {self.user_item_matrix.shape}")
                logger.info(f"Sparsity: {(self.user_item_matrix == 0).sum().sum() / (self.user_item_matrix.shape[0] * self.user_item_matrix.shape[1]):.2%}")
            else:
                logger.warning("No interactions found")
                self.user_item_matrix = pd.DataFrame()
                
        except Exception as e:
            logger.error(f"Error creating interaction matrix: {str(e)}")
            self.user_item_matrix = pd.DataFrame()
    
    def train_als_model(self):
        """Train ALS (Alternating Least Squares) model"""
        try:
            if self.user_item_sparse is None or self.user_item_sparse.shape[0] == 0:
                logger.warning("No interaction data available for training")
                return
            
            # Initialize ALS model with optimized parameters for food recommendation
            self.als_model = AlternatingLeastSquares(
                factors=64,          # Number of latent factors
                regularization=0.1,  # L2 regularization
                iterations=50,       # Number of training iterations
                alpha=40,           # Confidence scaling factor
                random_state=42,
                use_gpu=False
            )
            
            # Train the model
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
                return
            
            # Combine text features
            self.products_df['combined_features'] = (
                self.products_df['BrandName'].fillna('') + ' ' +
                self.products_df['Flavour'].fillna('') + ' ' +
                self.products_df['ProductType'].fillna('') + ' ' +
                self.products_df['Description'].fillna('') + ' ' +
                self.products_df['IngredientList'].fillna('') + ' ' +
                self.products_df['Originality'].fillna('')
            )
            
            # Create TF-IDF features
            tfidf = TfidfVectorizer(
                max_features=500,
                stop_words='english',
                ngram_range=(1, 2),
                min_df=1
            )
            
            tfidf_matrix = tfidf.fit_transform(self.products_df['combined_features'])
            
            # Add numerical features
            numerical_features = []
            
            # Spicy level (normalized)
            if 'SpicyLevel' in self.products_df.columns:
                spicy_normalized = self.products_df['SpicyLevel'].fillna(0) / 5.0  # Normalize to 0-1
                numerical_features.append(spicy_normalized.values.reshape(-1, 1))
            
            # Price (normalized)
            if 'PriceMYR' in self.products_df.columns:
                price_mean = self.products_df['PriceMYR'].mean()
                price_std = self.products_df['PriceMYR'].std()
                price_normalized = (self.products_df['PriceMYR'].fillna(price_mean) - price_mean) / price_std
                numerical_features.append(price_normalized.values.reshape(-1, 1))
            
            # Vegetarian flag
            if 'IsVegetarian' in self.products_df.columns:
                numerical_features.append(self.products_df['IsVegetarian'].fillna(0).values.reshape(-1, 1))
            
            # Combine all features
            if numerical_features:
                numerical_matrix = np.hstack(numerical_features)
                self.product_features = np.hstack([tfidf_matrix.toarray(), numerical_matrix])
            else:
                self.product_features = tfidf_matrix.toarray()
            
            # Calculate content similarity matrix
            self.content_similarity_matrix = cosine_similarity(self.product_features)
            
            logger.info(f"Content features created: {self.product_features.shape}")
            
        except Exception as e:
            logger.error(f"Error creating content features: {str(e)}")
            self.content_similarity_matrix = None
    
    def get_als_recommendations(self, user_id, n_recommendations=10):
        """Get recommendations using ALS collaborative filtering"""
        try:
            if (self.als_model is None or 
                self.user_item_matrix.empty or 
                user_id not in self.user_item_matrix.index):
                return []
            
            user_idx = self.user_item_matrix.index.get_loc(user_id)
            
            # Get recommendations from ALS model
            recommendations = self.als_model.recommend(
                user_idx, 
                self.user_item_sparse[user_idx], 
                N=n_recommendations,
                filter_already_liked_items=True
            )
            
            # Convert item indices back to product IDs
            product_ids = []
            confidence_scores = []
            
            for item_idx, confidence in recommendations:
                if item_idx < len(self.user_item_matrix.columns):
                    product_id = self.user_item_matrix.columns[item_idx]
                    product_ids.append(product_id)
                    confidence_scores.append(float(confidence))
            
            return list(zip(product_ids, confidence_scores))
            
        except Exception as e:
            logger.error(f"Error getting ALS recommendations: {str(e)}")
            return []
    
    def get_content_recommendations(self, product_id, n_recommendations=10):
        """Get content-based recommendations for similar products"""
        try:
            if self.content_similarity_matrix is None:
                return []
            
            product_idx = self.products_df[self.products_df['ProductID'] == product_id].index
            if len(product_idx) == 0:
                return []
            
            product_idx = product_idx[0]
            similarity_scores = self.content_similarity_matrix[product_idx]
            
            # Get top similar products (excluding the product itself)
            similar_indices = similarity_scores.argsort()[::-1][1:n_recommendations+1]
            similar_products = []
            
            for idx in similar_indices:
                product_id_similar = self.products_df.iloc[idx]['ProductID']
                similarity_score = similarity_scores[idx]
                similar_products.append((product_id_similar, float(similarity_score)))
            
            return similar_products
            
        except Exception as e:
            logger.error(f"Error getting content recommendations: {str(e)}")
            return []
    
    def get_popular_recommendations(self, n_recommendations=10, category=None):
        """Get popular items based on purchase frequency and recency"""
        try:
            if self.purchases_df.empty:
                # Fallback to random products if no purchase data
                popular_products = self.products_df.sample(min(n_recommendations, len(self.products_df)))
                return [(pid, 1.0) for pid in popular_products['ProductID'].tolist()]
            
            # Calculate popularity score
            now = datetime.now()
            popularity_scores = {}
            
            for _, purchase in self.purchases_df.iterrows():
                product_id = purchase['ProductID']
                quantity = purchase['Quantity']
                days_ago = (now - pd.to_datetime(purchase['OrderDate'])).days
                
                # Recency weight (more recent purchases have higher weight)
                recency_weight = max(0.1, 1.0 - (days_ago / 90.0))  # Decay over 90 days
                
                score = quantity * recency_weight
                popularity_scores[product_id] = popularity_scores.get(product_id, 0) + score
            
            # Filter by category if specified
            if category:
                filtered_products = self.products_df[
                    self.products_df['ProductType'].str.contains(category, case=False, na=False)
                ]['ProductID'].tolist()
                popularity_scores = {k: v for k, v in popularity_scores.items() if k in filtered_products}
            
            # Sort by popularity and return top N
            sorted_products = sorted(popularity_scores.items(), key=lambda x: x[1], reverse=True)
            return sorted_products[:n_recommendations]
            
        except Exception as e:
            logger.error(f"Error getting popular recommendations: {str(e)}")
            return []
    
    def get_hybrid_recommendations(self, user_id, n_recommendations=10):
        """Get hybrid recommendations combining ALS and content-based approaches"""
        try:
            recommendations = {}
            
            # Get ALS recommendations (70% weight)
            als_recs = self.get_als_recommendations(user_id, n_recommendations)
            for product_id, score in als_recs:
                recommendations[product_id] = recommendations.get(product_id, 0) + score * 0.7
            
            # Get content-based recommendations based on user's purchase history (20% weight)
            if not self.purchases_df.empty:
                user_purchases = self.purchases_df[self.purchases_df['UserID'] == user_id]['ProductID'].tolist()
                
                for product_id in user_purchases[-3:]:  # Use last 3 purchases
                    content_recs = self.get_content_recommendations(product_id, 5)
                    for rec_product_id, similarity in content_recs:
                        recommendations[rec_product_id] = recommendations.get(rec_product_id, 0) + similarity * 0.2
            
            # Add popular recommendations (10% weight)
            popular_recs = self.get_popular_recommendations(n_recommendations)
            for product_id, popularity in popular_recs:
                recommendations[product_id] = recommendations.get(product_id, 0) + popularity * 0.1
            
            # Sort by combined score and return top N
            sorted_recs = sorted(recommendations.items(), key=lambda x: x[1], reverse=True)
            return sorted_recs[:n_recommendations]
            
        except Exception as e:
            logger.error(f"Error getting hybrid recommendations: {str(e)}")
            return self.get_popular_recommendations(n_recommendations)
    
    def get_cold_start_recommendations(self, user_id, n_recommendations=10):
        """Handle cold start problem for new users"""
        try:
            # For new users, use popular items
            popular_recs = self.get_popular_recommendations(n_recommendations)
            return popular_recs
            
        except Exception as e:
            logger.error(f"Error getting cold start recommendations: {str(e)}")
            return []
    
    def get_product_details(self, product_recommendations):
        """Get detailed product information with scores"""
        try:
            if not product_recommendations:
                return []
            
            product_ids = [pid for pid, _ in product_recommendations]
            scores = {pid: score for pid, score in product_recommendations}
            
            products = self.products_df[
                self.products_df['ProductID'].isin(product_ids)
            ].to_dict('records')
            
            # Add recommendation scores and maintain order
            for product in products:
                product['recommendation_score'] = scores.get(product['ProductID'], 0)
            
            # Sort by recommendation score
            products.sort(key=lambda x: x['recommendation_score'], reverse=True)
            
            return products
            
        except Exception as e:
            logger.error(f"Error getting product details: {str(e)}")
            return []
    
    def log_interaction(self, user_id, product_id, interaction_type='view', metadata=None):
        """Log user interaction for real-time model updates"""
        try:
            # For demonstration, we'll just log to console
            # In production, you'd save to database and trigger model retraining
            logger.info(f"User {user_id} {interaction_type} product {product_id}")
            
            # You could implement real-time updates here
            # For now, we'll just track in memory for the session
            
        except Exception as e:
            logger.error(f"Error logging interaction: {str(e)}")
    
    def retrain_model(self):
        """Retrain the ALS model with updated data"""
        try:
            logger.info("Retraining ALS model...")
            self.load_data()
            self.create_interaction_matrix()
            self.train_als_model()
            self.create_content_features()
            logger.info("Model retrained successfully")
            return True
            
        except Exception as e:
            logger.error(f"Error retraining model: {str(e)}")
            return False
    
    def get_model_stats(self):
        """Get statistics about the recommendation system"""
        try:
            stats = {
                'total_products': len(self.products_df),
                'total_users': len(self.users_df),
                'total_purchases': len(self.purchases_df) if not self.purchases_df.empty else 0,
                'total_views': len(self.views_df) if not self.views_df.empty else 0,
                'user_item_matrix_shape': list(self.user_item_matrix.shape) if not self.user_item_matrix.empty else [0, 0],
                'als_model_trained': self.als_model is not None,
                'content_model_ready': self.content_similarity_matrix is not None,
                'sparsity': float((self.user_item_matrix == 0).sum().sum() / (self.user_item_matrix.shape[0] * self.user_item_matrix.shape[1])) if not self.user_item_matrix.empty else 0
            }
            return stats
            
        except Exception as e:
            logger.error(f"Error getting model stats: {str(e)}")
            return {}

# Initialize the recommendation system
rec_system = FoodRecommendationSystem()

# Flask API Routes
@app.route('/')
def index():
    return render_template('index.html')

@app.route('/api/recommend/<int:user_id>')
def recommend_for_user(user_id):
    """Get recommendations for a specific user"""
    try:
        n_recommendations = request.args.get('n', 10, type=int)
        rec_type = request.args.get('type', 'hybrid')
        
        # Check if user exists in training data
        user_exists = user_id in rec_system.user_item_matrix.index if not rec_system.user_item_matrix.empty else False
        
        if rec_type == 'als' and user_exists:
            recommendations = rec_system.get_als_recommendations(user_id, n_recommendations)
        elif rec_type == 'popular':
            recommendations = rec_system.get_popular_recommendations(n_recommendations)
        elif rec_type == 'hybrid' and user_exists:
            recommendations = rec_system.get_hybrid_recommendations(user_id, n_recommendations)
        else:
            # Cold start case
            recommendations = rec_system.get_cold_start_recommendations(user_id, n_recommendations)
        
        products = rec_system.get_product_details(recommendations)
        
        return jsonify({
            'success': True,
            'user_id': user_id,
            'user_exists': user_exists,
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
    app.run(debug=True, host='0.0.0.0', port=5000)