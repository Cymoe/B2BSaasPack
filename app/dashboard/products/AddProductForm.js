'use client'

import { useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { addProduct } from '../../actions/productActions';

export default function AddProductForm() {
  const [isPending, startTransition] = useTransition();
  const router = useRouter();

  const handleSubmit = async (formData) => {
    startTransition(async () => {
      await addProduct(formData);
      router.refresh();
    });
  };

  return (
    <form action={handleSubmit} className="space-y-2">
      <input
        type="text"
        name="name"
        placeholder="Product name"
        className="border p-2"
      />
      <input
        type="number"
        name="price"
        placeholder="Price"
        step="0.01"
        className="border p-2"
      />
      <button type="submit" className="bg-blue-500 text-white p-2" disabled={isPending}>
        {isPending ? 'Adding...' : 'Add Product'}
      </button>
    </form>
  );
}
