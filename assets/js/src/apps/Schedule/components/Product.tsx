import parse from 'html-react-parser';
import { Product } from "../types";
import Shipments from "./Shipments";
import Errors from "./Errors";

type props = {
    product: Product
}

export default function Product({product}: props)
{

    return (
        <div className="omom-flex omom-flex-col omom-gap-2 omom-bg-white omom-border-2 omom-border-[#bbb] omom-border-solid omom-rounded-sm omom-p-4 omom-mx-auto omom-mt-0 omom-mb-6">

            <h4 className="product-title">
                <span>{parse(product.name)}</span>
                <span><strong>#{product.id}</strong></span>
            </h4>

            {0 !== product.shipments.length ? <Shipments shipments={product.shipments} /> : ""}

            <table className="widefat fixed" cellSpacing="0">
                <thead>
                    <tr>
                        <th className="manage-column column-instock" scope="col">In stock</th>
                        <th className="manage-column column-total" scope="col">Total pre-order stock</th>
                        <th className="manage-column column-totalav" scope="col">Total available</th>
                        <th className="manage-column column-postock" scope="col">Pre-order stock</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td className="column-instock">{product.inStock}</td> 
                        <td className="column-total">{product.totalPreOrderStock}</td>
                        <td className="column-totalav">{product.totalAvPreOrderStock}</td>
                        <td className="column-postock">{product.preOrderStock}</td>
                    </tr>
                </tbody>
            </table>

            <Errors errors={product.errors} />
        </div>
    )
}
