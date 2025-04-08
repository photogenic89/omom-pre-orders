import { ajax } from "../../../utils/_fetch";
import { Errors } from "../types";

interface Props {
    errors: Errors
}

export default function Errors({errors}: Props)
{
    const fixError = (method: string, id: string) => {
        ajax( 'Schedule', method, {id: id} ).then(response => {
            console.log(response);
        })
    }

    const getErrors = () => 
    {           
        return errors.map((error, i: number) => {
            return (
                <div className="error-field">
                    <span className="hidden-id-ref">{error.id}</span>
                    <span className="hidden-issue-ref">{error.option}</span>
                    <span className="error-note">Issue: {error.note}</span>
                    <span 
                        className="error-solve" 
                        onClick={e => fixError(error.option, error.id)}
                    >
                        FIX
                    </span>
                </div>
            )
        });
    };

    return (
        <div>
            {getErrors()}
        </div>
    )
}