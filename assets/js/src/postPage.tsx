import { createRoot } from "@wordpress/element";
import ProductsInShipment from "./apps/ProductsInShipment";

const root = createRoot(document.getElementById('products-root')!);
if (undefined !== root) root.render( <ProductsInShipment /> );