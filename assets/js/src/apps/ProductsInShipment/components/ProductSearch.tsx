import { useState } from "@wordpress/element";
import { ajax } from "../../../utils/_fetch";
import { Products } from "../types";

interface Props {
    index: number
    id: string
    name: string
    namespace: string
    allProducts: Products
    onAddProduct: (id: string, title: string) => void
}

type Suggestions = Array<Suggestion>;

interface Suggestion {
    id: number
    title: string
}

export default function ProductSearch(props: Props)
{
    const [id, setId]                   = useState(props.id);
    const [title, setTitle]             = useState(props.name);
    const [search, setSearch]           = useState("");
    const [suggestions, setSuggestions] = useState<Suggestions>([]);

    const clear = () => 
    {
        setSuggestions([]);
        setId("");
        setTitle("");
        setSearch("");
    }

    /**
     * When one types something into the search field, look for a product
     * 
     * @param e 
     * @returns 
     */
    const handleInputChange = ( e: {target: HTMLInputElement}) =>
    {
        const searchValue = e.target.value;
        setSearch(searchValue);

        // if it has less than three characters, do not use ajax
        if (! (/.{3,}/.test(searchValue))) return;

        ajax(
            "Post", 
            'searchForSuggestions', 
            {term: searchValue}
        ).then(response => {
            setSuggestions(response.data ?? []);
        })
    }

    /**
     * adds the product, which then removes the productSearch
     * @param productId 
     * @param productTitle the name contains also the id and stock
     */
    const addProduct = (productId: string, productName: string) =>
    {
        // dont add, if already exists
        const exists = -1 !== props.allProducts.findIndex(p => p.id === (productId + ""))
        const clear = props.index > props.allProducts.length;

        setSuggestions([]);
        setId(clear ? "" : productId);
        setTitle(clear ? "" : productName);

        if (exists) {
            setSearch("already exists");
            return;
        }

        if (clear) setSearch("");

        props.onAddProduct(productId, productName);
    }

    /**
     * If a product is not linked yet, show this search
     */
    const getSuggestions = () =>
    {
        return suggestions.map((suggestion: Suggestion) => {
            const productId    = suggestion.id + "";
            const productTitle = suggestion.title;
            return (
                <li 
                    className="omom-p-2 hover:omom-cursor-pointer" 
                    onClick={() => addProduct(productId, productTitle)} 
                    key={productId}
                >
                    {productTitle}
                </li>
            )
        });
    }

    return (
        <div className="omom-relative omom-w-full">
            <input 
                type="hidden"
                name={props.namespace}
                value={id}
            />
            <input
                style={{width:"100%"}}
                onChange={handleInputChange}
                type="text" 
                placeholder="Search for a product here"
                value={"" !== title ? title : search}
            />
            <span
                className="omom-absolute omom-top-1 omom-right-2 hover:omom-cursor-pointer"
                onClick={clear}
            >
                X
            </span>
            <ul 
                className={0 < suggestions.length ? "omom-absolute omom-z-10 omom-flex omom-flex-col omom-border omom-border-solid omom-border-[#ddd] omom-bg-white" : "omom-m-0"}
                style={{width:"calc(100% - 2px)"}}
            >
                {getSuggestions()}
            </ul>
        </div>
    );
}