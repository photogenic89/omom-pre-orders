export type Products = Product[]

export type Product = {
    errors: Errors
    id: number
    name: string
    totalPreOrderStock: number
    totalAvPreOrderStock: number
    preOrderStock: number
    inStock: number
    shipments: Shipments
}

export type Shipments = Shipment[]

export type Shipment = {
    arrival: number
    id: number
    original: number
    available: number
}

export type Errors = Error[];

type Error = {
    id: string
    option: string
    note: string
}