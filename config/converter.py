# Hey! This is a script to batch process images.
# It removes the background and resizes them to a standard size.
# Make sure you've installed the necessary libraries first:
# pip install pillow rembg

import os
from PIL import Image
from rembg import remove

def process_images(input_dir, output_dir, target_size=(500, 500)):
    """
    Processes all images in a directory by removing their background and resizing them.

    Args:
        input_dir (str): The path to the directory containing the original images.
        output_dir (str): The path to the directory where processed images will be saved.
        target_size (tuple): A tuple (width, height) for the final image size.
    """
    print(f"Starting image processing...")
    print(f"Input folder: {input_dir}")
    print(f"Output folder: {output_dir}")
    print(f"Target size: {target_size}")

    # Create the output directory if it doesn't exist already.
    # It's good practice so the script doesn't crash if the folder is missing.
    if not os.path.exists(output_dir):
        os.makedirs(output_dir)
        print(f"Created output directory: {output_dir}")

    # Get a list of all files in the input directory.
    for filename in os.listdir(input_dir):
        # Let's make sure we're only working with image files.
        # You can add more extensions here if you need to (like .jpeg, .gif, etc.)
        if filename.lower().endswith(('.png', '.jpg', '.jpeg')):
            input_path = os.path.join(input_dir, filename)
            # We'll save the new file with a little suffix to show it's been processed.
            # And we'll save it as a PNG to keep the transparent background.
            output_filename = f"{os.path.splitext(filename)[0]}.png"
            output_path = os.path.join(output_dir, output_filename)

            try:
                print(f"\nProcessing '{filename}'...")

                # Open the image file.
                with Image.open(input_path) as img:
                    # Step 1: Remove the background.
                    # The 'remove' function from rembg does all the magic here.
                    img_no_bg = remove(img)
                    print(f"-> Background removed.")

                    # Step 2: Resize the image.
                    # We use the LANCZOS filter because it gives a high-quality result.
                    img_resized = img_no_bg.resize(target_size, Image.Resampling.LANCZOS)
                    print(f"-> Resized to {target_size}.")

                    # Step 3: Save the final image.
                    img_resized.save(output_path)
                    print(f"-> Saved processed image to '{output_path}'")

            except Exception as e:
                # If something goes wrong with a file, we'll print an error
                # and just skip to the next one.
                print(f"!! Could not process {filename}. Error: {e}")

    print("\nAll done! Check your output folder.")

# --- CONFIGURATION ---
# You just need to change these paths to match your folders.
# I've set them to 'input_images' and 'output_images' as examples.

# The folder where your original images are.
INPUT_IMAGE_DIRECTORY = 'sources/products'

# The folder where you want to save the processed images.
OUTPUT_IMAGE_DIRECTORY = 'output_images'

# The size you want all your final images to be (width, height).
RESIZE_DIMENSIONS = (1563, 1563)

# --- RUN THE SCRIPT ---
# This part calls the function with your settings.
if __name__ == "__main__":
    # Just a little check to make sure the input directory exists before we start.
    if not os.path.exists(INPUT_IMAGE_DIRECTORY):
        print(f"Error: The input directory '{INPUT_IMAGE_DIRECTORY}' was not found.")
        print("Please create it and add your images, or change the INPUT_IMAGE_DIRECTORY variable.")
    else:
        process_images(INPUT_IMAGE_DIRECTORY, OUTPUT_IMAGE_DIRECTORY, RESIZE_DIMENSIONS)
