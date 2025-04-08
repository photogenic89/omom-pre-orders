import { useEffect, useState } from "@wordpress/element";
import { Product as ProductType, Products } from "./types";
import Product from "./components/Product";
import { ajax } from "../../utils/_fetch";

export default function ProductsInShipment()
{
    const postId = (document.getElementById('post_ID') as HTMLInputElement)?.value;
    const [products, setProducts] = useState<Products>([]);
    const [isLoaded, setIsLoaded] = useState(false);

    useEffect(() => {
        ajax( 'Post', 'getProducts', {postId: postId} ).then(response => {
            if ("products" in response.data) {
                const newProducts = response.data.products.filter((product: ProductType) => "id" in product);

                console.log("newProducts", response.data)
                setProducts(newProducts);
                setIsLoaded(true);
            }
        })
    }, []);

    const getProducts = () => {
        return products.map((product, i) => <Product 
            index={i} 
            product={product}    
            allProducts={products}
            onAddProduct={addProduct}
            onChangeCurrent={changeProduct}
            onRemoveCurrent={(i) => removeCurrentProduct(i)} 
        />)
    }

    const addProduct = (newProduct: ProductType) =>
    {
        console.log("trying add", newProduct);
        const newProducts = products;
        newProducts.push(newProduct)
        setProducts([...newProducts]);
    }

    const changeProduct = (index: number, id: string, title: string) => 
    {
        console.log("trying update", id);
        const newProducts = products.map((product, i) => {
            if (i === index) {
                product.id = id + "";
                product.name = title;
            }
            return product;
        })

        setProducts(newProducts);
    }

    const removeCurrentProduct = (index: number) => 
    {
        setProducts(products.filter((p, i) => index !== i));
    }

    return (
        <div id="rs-form">
            <input 
                type="hidden"
                name="omom_products_loaded"
                value={isLoaded ? 1 : 0}
            />
            <section className="wc-backbone-modal-main" role="main">	    
                <article>
                    <table className="widefat">

                        <thead>
                            <tr>
                                <th className="product_nr">Nr.</th>
                                <th className="on_fs_page">F.S.</th>
                                <th className="search">Product</th>
                                <th className="quantity">Pre-order quantity</th>
                                <th className="restock">Available quantity</th>
                                <th className="remove">Remove</th>
                            </tr>
                        </thead>

                        <tbody data-row="" className="product-table">
                            <input 
                                type="hidden" 
                                id="product_counter" 
                                name="rs_amount_products" 
                                value={products.length}
                            />

                            {getProducts()}
                            <Product
                                index={products.length + 1}
                                product={{
                                    id: "",
                                    OriginalQty: 0,
                                    AvailableQty: 0,
                                    onFutureStockPage: false,
                                    name: "",
                                    state: "new"
                                }}
                                allProducts={products}
                                onAddProduct={addProduct}
                                onChangeCurrent={changeProduct}
                                onRemoveCurrent={(i) => removeCurrentProduct(i)}
                            />                            
                        </tbody>

                    </table>
                </article>
            </section>
        </div>
    )
}