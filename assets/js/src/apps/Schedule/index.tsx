import Products from "./components/Products";

export default function Schedule()
{
    return (
        <div className="omom-pr-8 omom-mt-8">
            <div className="omom-bg-white omom-flex omom-flex-col omom-gap-2 omom-p-4">
                <h2 className="page-header">Schedule</h2>
                <p className="page-description omom-mb-2">Show pre-order stock of all products in shipments.</p>

                <Products />
            </div>
        </div>
    )
}