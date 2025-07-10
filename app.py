import pandas as pd
import numpy as np
from flask import Flask, request, jsonify
from flask_cors import CORS # Import CORS
from sqlalchemy import text # Import text for raw SQL execution
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
# This line is crucial to allow your PHP page to make requests to this API
CORS(app) 

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class FoodRecommendationSystem:
    def __init__(self):
        logger.info("Initializing Food Recommendation System...")
        self.db_engine = self.create_db_engine()
        
        self.products_df = pd.DataFrame()
        self.users_df = pd.DataFrame()
        self.purchases_df = pd.DataFrame()
        self.views_df = pd.DataFrame()
        
        self.user_id_to_idx = {}
        self.idx_to_user_id = {}

        self.als_model = None
        self.user_item_matrix = None
        self.user_item_sparse = None
        self.content_similarity_matrix = None
        self.weights = {'view': 1.0, 'purchase': 5.0}

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
        try:
            # IMPORTANT: Replace with your actual database connection string
            connection_string = f"mysql+pymysql://root@127.0.0.1:3307/ecommercedb"
            engine = sqlalchemy.create_engine(connection_string)
            engine.connect()
            logger.info("SQLAlchemy database engine created successfully.")
            return engine
        except Exception as e:
            logger.error(f"FATAL: Could not create database engine. Error: {e}")
            return None

    def load_all_data(self):
        logger.info("Loading all data from database...")
        try:
            self.products_df = pd.read_sql_query("SELECT * FROM products", self.db_engine)
            self.users_df = pd.read_sql_query("SELECT * FROM users", self.db_engine)
            self.purchases_df = pd.read_sql_query("SELECT o.UserID, o.OrderDate, oi.ProductID, oi.Quantity FROM orders o JOIN order_items oi ON o.OrderID = oi.OrderID WHERE o.OrderStatus = 'Paid'", self.db_engine)
            self.views_df = pd.read_sql_query("SELECT UserID, ProductID, ViewTimestamp FROM product_views", self.db_engine)
            
            logger.info(f"Loaded {len(self.products_df)} products, {len(self.purchases_df)} purchase records and {len(self.views_df)} view records from DB.")

            for df in [self.products_df, self.purchases_df, self.views_df]:
                if 'ProductID' in df.columns:
                    df['ProductID'] = pd.to_numeric(df['ProductID'], errors='coerce')
                    df.dropna(subset=['ProductID'], inplace=True)
                    df['ProductID'] = df['ProductID'].astype(int)

        except Exception as e:
            logger.error(f"FATAL: Error during data loading. Error: {e}")

    # ... other class methods (create_interaction_matrix, train_als_model, etc.) remain the same ...
    def create_interaction_matrix(self):
        try:
            interactions = []
            active_products = self.products_df[self.products_df['IsActive'] == 1]['ProductID'].unique()
            
            if not self.purchases_df.empty:
                purchases = self.purchases_df[self.purchases_df['ProductID'].isin(active_products)]
                interactions.append(purchases.rename(columns={'Quantity': 'score'})[['UserID', 'ProductID', 'score']].assign(score=lambda x: x['score'] * self.weights['purchase']))
            
            if not self.views_df.empty:
                views = self.views_df[self.views_df['ProductID'].isin(active_products)]
                view_interactions = views.groupby(['UserID', 'ProductID']).size().reset_index(name='score')
                interactions.append(view_interactions.assign(score=lambda x: x['score'] * self.weights['view']))
            
            if interactions:
                all_interactions = pd.concat(interactions, ignore_index=True)
                combined_interactions = all_interactions.groupby(['UserID', 'ProductID'])['score'].sum().reset_index()
                
                unique_user_ids = combined_interactions['UserID'].unique()
                self.user_id_to_idx = {user_id: i for i, user_id in enumerate(unique_user_ids)}
                self.idx_to_user_id = {i: user_id for user_id, i in self.user_id_to_idx.items()}
                
                combined_interactions['user_idx'] = combined_interactions['UserID'].map(self.user_id_to_idx)

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
        try:
            if self.products_df.empty:
                logger.warning("No product data for content features.")
                return
            
            content_df = self.products_df[self.products_df['IsActive'] == 1].copy()
            content_df['combined_features'] = content_df.astype(str).fillna('').agg(' '.join, axis=1)
            
            tfidf = TfidfVectorizer(max_features=500, stop_words='english')
            self.product_features = tfidf.fit_transform(content_df['combined_features'])
            self.content_similarity_matrix = cosine_similarity(self.product_features)
            self.content_product_indices = content_df.index
            self.content_product_ids = content_df['ProductID'].tolist()
            logger.info(f"Content features created: {self.product_features.shape}")
            
        except Exception as e:
            logger.error(f"Error creating content features: {str(e)}")

# Initialize the system
rec_system = FoodRecommendationSystem()

# --- API Routes ---

@app.route('/api/products')
def get_all_products():
    """Get all available products for filter population."""
    try:
        products = rec_system.products_df[rec_system.products_df['IsActive'] == 1].to_dict('records')
        return jsonify({'success': True, 'products': products})
    except Exception as e:
        logger.error(f"Error in get_all_products: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/recommend/filtered', methods=['GET'])
def recommend_filtered():
    """Get recommendations based on user-defined filters."""
    try:
        filtered_df = rec_system.products_df.copy()
        
        # --- Apply filters ---
        if request.args.get('brand_name'):
            filtered_df = filtered_df[filtered_df['BrandName'] == request.args.get('brand_name')]
        if request.args.get('product_type'):
            filtered_df = filtered_df[filtered_df['ProductType'] == request.args.get('product_type')]
        if request.args.get('originality') and 'Originality' in filtered_df.columns:
            filtered_df = filtered_df[filtered_df['Originality'] == request.args.get('originality')]
        if request.args.get('spicy_level') is not None:
            filtered_df['SpicyLevel'] = pd.to_numeric(filtered_df['SpicyLevel'], errors='coerce')
            filtered_df = filtered_df[filtered_df['SpicyLevel'] <= request.args.get('spicy_level', type=int)]
        if request.args.get('min_price') is not None:
            filtered_df = filtered_df[filtered_df['PriceMYR'] >= request.args.get('min_price', type=float)]
        if request.args.get('max_price') is not None:
            filtered_df = filtered_df[filtered_df['PriceMYR'] <= request.args.get('max_price', type=float)]
        if request.args.get('availability') is not None:
            filtered_df = filtered_df[filtered_df['IsActive'] == request.args.get('availability', type=int)]

        recommendations = filtered_df.head(20).to_dict('records')
        return jsonify({'success': True, 'recommendations': recommendations})
    except Exception as e:
        logger.error(f"Error in recommend_filtered: {str(e)}")
        return jsonify({'success': False, 'error': str(e)}), 500

# ### NEW ENDPOINT TO LOG PRODUCT VIEWS ###
@app.route('/api/log_view', methods=['POST'])
def log_view():
    """Logs a user viewing a product."""
    try:
        data = request.get_json()
        if not data or 'user_id' not in data or 'product_id' not in data:
            return jsonify({'success': False, 'error': 'Missing user_id or product_id'}), 400

        user_id = data['user_id']
        product_id = data['product_id']
        
        # Use a connection from the engine to execute the query
        with rec_system.db_engine.connect() as connection:
            # Use text() to create a SQL expression that can be safely executed
            # Use bind parameters for security against SQL injection
            stmt = text("""
                INSERT INTO product_views (UserID, ProductID, ViewTimestamp) 
                VALUES (:user_id, :product_id, :timestamp)
            """)
            connection.execute(stmt, {
                "user_id": user_id, 
                "product_id": int(product_id), # Ensure product_id is an integer
                "timestamp": datetime.datetime.now()
            })
            connection.commit() # Commit the transaction

        logger.info(f"Logged view for user '{user_id}' on product '{product_id}'")
        return jsonify({'success': True})

    except Exception as e:
        logger.error(f"Error in log_view: {str(e)}")
        # Handle cases like duplicate entry gracefully without sending an error to the client
        if "Duplicate entry" in str(e) or "UNIQUE constraint failed" in str(e):
             return jsonify({'success': True, 'message': 'View already logged for this session.'})
        return jsonify({'success': False, 'error': 'Could not log view.'}), 500

if __name__ == '__main__':
    logger.info("Initializing Food Recommendation System...")
    if rec_system.db_engine is not None:
        logger.info("Starting Flask application server...")
        app.run(debug=True, host='0.0.0.0', port=5000)
    else:
        logger.error("Could not start Flask server because database engine failed to initialize.")
