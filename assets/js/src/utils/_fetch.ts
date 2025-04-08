interface RequestInit {
    method: "GET" | "POST"
    headers: {
        "X-WP-Nonce": string
        "Nonce": string
        "Content-Type"?: string
        "Cache-Control": RequestCache
    }
    body?: string
}

interface Response {
    data: {
        [key: string]: any
    },
    success: boolean
}

interface Data {
    body?: body
    nonce: string
    method: methods
    init?: init
}

type body = {[key: string]: any};
type methods = 'GET' | 'POST';
type init = {[key: string]: any};
type pages = 'Schedule' | 'Post' | 'Settings';

interface Ajax {
    ajaxUrl: string;
    nonce: string;
    post_id?: string;
}   

// object created in WP
declare const omomPreOrdersObject: Ajax;

/**
 * 
 * @param url 
 * @param data 
 * @returns 
 */
export async function asyncFetch( url: string, data: Data )
{
    let response: Response = {
        data: {},
        success: false
    };

    try {
        // Prepare the options object for fetch
        let init: RequestInit = {
            method: data.method,
            headers: {
                'X-WP-Nonce': data.nonce,
                'Nonce': data.nonce,
                "Cache-Control": "no-cache",
            }
        };

        if ("init" in data) init = {...init, ...data.init};

        // Only add the Content-Type header and body if the method is not GET
        if (data.method !== "GET") {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(data.body);
        }

        const fetched = await fetch( url, init);

        if (! fetched.ok) {
            throw new Error(`An error has occured: ${fetched.status}`);
        }

        const json = await fetched.json();

        return json;
    } catch (error) {
        console.error('Error: ', error);
        return response;
    }
}

/**
 * Call wp ajax 
 * 
 * @param pages
 * @param method the method to call
 * @param body the body of data to send
 * @param newNonce overwrite the nonce
 * @returns a Promise object
 */
export function ajax( page: pages, method: string, body: {[key: string]: any} = {} )
{
    let url = omomPreOrdersObject.ajaxUrl 
        + "?action=omom_preorders_request" 
        + "&ajax_nonce=" + omomPreOrdersObject.nonce
        + "&page=" + page
        + "&method=" + method;

    if (undefined !== omomPreOrdersObject.post_id) url += "&post_id=" + omomPreOrdersObject.post_id;

    const data: Data = {
        nonce: omomPreOrdersObject.nonce,
        body: body,
        method: 'POST'
    }

    return asyncFetch(url, data );
}