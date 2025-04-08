import { SearchControl } from '@wordpress/components';
import { useState } from "@wordpress/element";
import { Products } from "../types";

type props = {
    classes?: string
    products: Products
    onFoundIndex: (index: number) => void
}

export default function Search({classes = "", products, onFoundIndex}: props) 
{
    const [productId, setProductId] = useState<string>('');

    function handleSearch(value: string) {
        setProductId(value);

        if (value.match(/^[0-9]+$/) === null) return;

        onFoundIndex(products.findIndex(product => product.id === +value));
    }

    return (
        <SearchControl
            className={classes}
            value={ productId }
            placeholder="Search by product ID"
            onChange={searchValue => handleSearch(searchValue)}
        />
    );
}