export type Products = Product[]

export interface Product {
    id: string,
    OriginalQty: number,
    AvailableQty: number,
    onFutureStockPage: boolean,
    name: string,
    state: "old"|"new" // when a product is added for a first time or has been saved before
}