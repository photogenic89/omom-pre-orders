import { useEffect, useState } from "@wordpress/element";
import { Product as TypeProduct } from "../types";
import { ajax } from "../../../utils/_fetch";
import Product from "./Product";
import Search from "./Search";
import Loader from "@/utils/Loader";

export default function Products()
{
    const [products, setProducts] = useState([]);
    const [index, setActiveIndex] = useState(-1);
    const [loading, setLoading]   = useState(true);

    useEffect(() => {
        ajax( 'Schedule', 'getProducts' ).then(response => {
            console.log("getProducts", response)
            if ("products" in response.data) setProducts(response.data.products);
            setLoading(false);
        })
    }, []);

    const getProducts = () =>
    {
        return products.map((product: TypeProduct, i: number) => (-1 === index || index === i) ? <Product product={product} /> : "");
    }

    return (
        <div className="omom-p-4 omom-relative">
            <Loader loading={loading}/>
            <Search
                classes="omom-mb-4"
                products={products} 
                onFoundIndex={(index: number) => setActiveIndex(index)}
            />
            <div className="omom-grid omom-grid-cols-2 omom-gap-4 omom-mr-4">
                {getProducts()}
            </div>
        </div>
    )
}