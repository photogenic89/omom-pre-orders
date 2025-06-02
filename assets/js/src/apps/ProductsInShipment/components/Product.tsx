import { useState } from "@wordpress/element";
import { Products, Product as ProductType } from "../types"
import ProductSearch from "./ProductSearch";

interface Props {
    index: number
    product: ProductType
    allProducts: Products
    onRemoveCurrent: (i: number) => void
    onAddProduct: (product: ProductType) => void
    onChangeCurrent: (index: number, id: string, title: string) => void
}
export default function Product({index, product, allProducts, onAddProduct, onChangeCurrent, onRemoveCurrent}: Props)
{
    const [data, setData] = useState(product);
    const [shortcode, setShortcode] = useState(product.shortcode);
    const [qty, setQty]   = useState(product.OriginalQty);
    const [aQty, setAQty] = useState(+product.AvailableQty);

    /**
     * if is last one, append to products array. otherwise change out data here
     */
    const addProduct = (id: string, title: string) => 
    {
        if (index <= allProducts.length) {
            onChangeCurrent( index, id, title );
            return;
        }

        onAddProduct({
            id: id,
            name: title,
            AvailableQty: aQty,
            OriginalQty: qty,
            shortcode: shortcode,
            state: "new"
        });
        setAQty(0);
        setQty(0);
        setShortcode(false);
    }

    const onChangeQty = (newQty: number) => 
    {
        if ("new" === product.state) {
            setAQty(newQty);
        } else {
            const diff = newQty - qty;
            setAQty(aQty + diff);
        }

        setQty(newQty);
    }

    return (
        <tr id={"rs_product_" + index} className="rs_products">

            <td className="product_nr">
                #{index + 1}
            </td> 

            <td className="shortcode-table-cell">
                <input 
                    className="shortcode-input" 
                    type="checkbox" 
                    name={"rs_products[" + index + "][shortcode]"} 
                    checked={shortcode}
                    onClick={e => setShortcode(! shortcode)}
                />
            </td>

            <td className="search">
                <ProductSearch 
                    index={index}
                    id={product.id} 
                    name={product.name}
                    namespace={"rs_products[" + index + "][ID]"}
                    allProducts={allProducts}
                    onAddProduct={addProduct}
                />
            </td>

            <td className="quantity">
                <input 
                    className="quantity original_quantity" 
                    type="number" 
                    step="1" 
                    min="0" 
                    max="9999" 
                    autoComplete="off" 
                    name={"rs_products[" + index + "][Original]"}
                    placeholder="1" 
                    size={4}
                    value={qty}
                    onChange={v => onChangeQty(+v.target.value)} 
                />
            </td>

            <td className="restock omom-relative">
                <input
                    type="hidden" 
                    name={"rs_products[" + index + "][Restock]" }
                    value={aQty}
                />
                <input 
                    className="quantity product_stock" 
                    type="number" 
                    step="1" 
                    min="0" 
                    max="9999" 
                    autoComplete="off" 
                    placeholder="" 
                    size={4}
                    value={aQty}
                    disabled
                />
            </td>

            <td className="remove">{
                <input 
                    type={index > allProducts.length ? "hidden" : "button"} 
                    className="button-primary" 
                    onClick={() => onRemoveCurrent(index)}
                    value="X"
                />}
                
            </td>
        </tr>
    )
}