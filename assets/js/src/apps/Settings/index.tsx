import { useEffect, useState } from "@wordpress/element";
import { ajax } from "../../utils/_fetch";

export default function Settings()
{
    const [settings, setSettings] = useState([]);

    useEffect(() => {
        ajax( 'Settings', 'getSettings' ).then(response => {
            console.log("getSettings", response)
            if ("settings" in response.data) setSettings(response.data.settings);
        })
    }, []);

    const changeSetting = (method: string) => {
        ajax( 'Settings', method ).then(response => {
            console.log("getSettings", response)
            if ("settings" in response.data) setSettings(response.data.settings);
        })
    }

    const getSettings = () => {
        return settings.map(setting => {

            const isChecked = setting.isChecked;

            if ("button" === setting.type) {
                return (
                    <button
                        type="button"
                        onClick={e => changeSetting(setting.method)}
                    >
                        {setting.label}
                    </button>
                )
            }

            return (
                <div className="flex items-center mb-4">
                    <input 
                        id={setting.id} 
                        type="checkbox" 
                        value={isChecked ? "1" : ""}
                        checked={isChecked}
                        className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500"
                    />
                    <label 
                        htmlFor={setting.id} 
                        className="ms-2 text-sm font-medium text-gray-900"
                    >
                        {setting.label}
                    </label>
                </div>
            );
        })
    }

    return (
        <div>
            <h2 className="page-header">
                Settings
            </h2>
            {getSettings()}
        </div>
    )
}