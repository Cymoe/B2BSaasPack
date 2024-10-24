import { getProducts } from '../../actions/productActions';
import AddProductForm from './AddProductForm';

export default async function Products() {
  const products = await getProducts();

  return (
    <div className="p-4">
      <h1 className="text-2xl font-bold mb-4">Products</h1>
      <ul className="mb-4">
        {products.map((product) => (
          <li key={product._id}>{product.name} - ${product.price}</li>
        ))}
      </ul>
      <AddProductForm />
    </div>
  );
}
