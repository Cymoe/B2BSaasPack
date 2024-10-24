import mongoose from 'mongoose';

const productSchema = new mongoose.Schema({
  name: {
    type: String,
    required: true,
    trim: true,
  },
  price: {
    type: Number,
    required: true,
    min: 0,
  },
  // Add any other fields you need for products
}, { timestamps: true });


export const Product = mongoose.models.Product || mongoose.model('Product', productSchema);
