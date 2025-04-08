import { createRoot } from "@wordpress/element";
import Settings from "./apps/Settings";

const root = createRoot(document.getElementById('settings-root')!);
if (undefined !== root) root.render( <Settings /> );