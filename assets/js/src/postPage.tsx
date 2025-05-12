import { createRoot } from "@wordpress/element";
import ProductsInShipment from "./apps/ProductsInShipment";

const root = createRoot(document.getElementById('products-root')!);
if (undefined !== root) root.render( <ProductsInShipment /> );

// dialog box for when we release stock
const dialog = document.getElementById('omom_release_modal') as HTMLDialogElement;

console.log("found dialog", dialog);

document.getElementById( "omom_release_button" ).addEventListener( "click", (e) => {
    e.preventDefault();
    dialog.showModal();
});

document.getElementById('omom_abort_release').addEventListener( "click", () => {
    dialog.close();
})