'use server'

import dbConnect from '../../lib/dbConnect';
import { Product } from '../models/Product';

export async function getProducts() {
  await dbConnect();
  const products = await Product.find({}).lean();
  return products.map(product => ({
    id: product._id.toString(),
    name: product.name,
    price: product.price,
    createdAt: product.createdAt ? product.createdAt.toISOString() : null,
    updatedAt: product.updatedAt ? product.updatedAt.toISOString() : null
  }));
}

export async function addProduct(formData) {
  try {
    await dbConnect();
    const productData = {
      name: formData.get('name'),
      price: parseFloat(formData.get('price')),
    };

    const product = await Product.create(productData);
    return {
      id: product._id.toString(),
      name: product.name,
      price: product.price,
      createdAt: product.createdAt.toISOString(),
      updatedAt: product.updatedAt.toISOString()
    };
  } catch (error) {
    console.error("Error adding product:", error);
    throw error;
  }
}
