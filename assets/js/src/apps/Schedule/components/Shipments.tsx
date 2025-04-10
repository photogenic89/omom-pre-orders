import { Shipments } from "../types";

type props = {
    shipments: Shipments
}

export default function Shipments({shipments}: props)
{
    const getShipments = () =>
    {
        return shipments.map((shipment, i) => {

            const time = new Date(shipment.arrival * 1000);

            return (
                <>
                    <tr key={i}>
                        <td className="column-id">#{shipment.id}</td>
                        <td className="column-quantity">{shipment.original}</td>
                        <td className="column-quantity">{shipment.available}</td>
                        <td className="column-arrival">{time.toLocaleDateString('dk')}</td> 
                    </tr>
                    {shipment.arrival > Date.now() && (
                        <tr>
                            &#8627; shipment ready to release
                        </tr>
                    )}
                </>
            )
        })
    }   

    return (
        <table className="widefat fixed" cellSpacing="0">
            <thead>
                <tr>
                    <th id="shipment-id-table-head" className="manage-column column-id" scope="col">Shipment ID</th>
                    <th id="quantity-table-head" className="manage-column column-quantity" scope="col">Pre-order quantity</th>
                    <th id="quantity-table-head" className="manage-column column-quantity" scope="col">Available quantity</th>
                    <th id="arrival-table-head" className="manage-column column-arrival" scope="col">Arrival Date</th>
                </tr>
            </thead>
            <tbody>
                {getShipments()}
            </tbody>
        </table>
    );
}