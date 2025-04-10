export default function Glossar()
{
    const rows = [
        {
            key: "A",
            value: "total pre-order stock",
            formula: "",
            definition: "all pre-order quantities of all active shipments"
        },
        {
            key: "B",
            value: "total available",
            formula: "",
            definition: "all pre-order available quantities of all active shipments"
        },
        {
            key: "C",
            value: "stock",
            formula: "stock < 0 ? 0 : stock",
            definition: "how many of a product are on location"
        },
        {
            key: "D",
            value: "in stock",
            formula: "stock - (A - B)",
            definition: "the stock but taking in account how much was oversold through pre-ordering"
        },
        {
            key: "E",
            value: "pre-order stock",
            formula: "D === B",
            definition: "the value of how many still can be pre-ordered saved in the product. should always be equal the total available amount."
        },
        {
            key: "F",
            value: "pre-orders sold",
            formula: "A - B",
            definition: "how many of a product were sold from active shipments."
        },
        {
            key: "G",
            value: "active shipment",
            formula: "",
            definition: "A shipment which pre-order quantity was not released to stock yet."
        },
    ];

    const getTableRows = () => {    
        return rows.map(row => {
            return (
                <tr>
                    <td className="column-key" colSpan={1} >{row.key}</td> 
                    <td className="column-value" colSpan={2} >{row.value}</td> 
                    <td className="column-formula" colSpan={2} >{row.formula}</td> 
                    <td className="column-definition" colSpan={4} >{row.definition}</td>
                </tr>
            );
        })
    }

    return (
        <div className="omom-flex omom-flex-col omom-gap-2 omom-bg-white omom-border-2 omom-border-[#bbb] omom-border-solid omom-rounded-sm omom-p-4 omom-mx-auto omom-mt-0 omom-mb-6">

            <h4 className="product-title">
                <span>Glossar</span>
                <span><strong>#product id</strong></span>
            </h4>

            <table className="widefat fixed" cellSpacing="0">
                <thead>
                    <tr>
                        <th className="manage-column column-key" scope="col" colSpan={1} >Key</th>
                        <th className="manage-column column-value" scope="col" colSpan={2} >Value</th>
                        <th className="manage-column column-formula" scope="col" colSpan={3} >Formula</th>
                        <th className="manage-column column-definition" scope="col" colSpan={4} >Definition</th>
                    </tr>
                </thead>
                <tbody>
                    {getTableRows()}
                </tbody>
            </table>
        </div>
    );
}