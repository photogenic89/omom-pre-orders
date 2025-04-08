import { createRoot } from "@wordpress/element";
import Schedule from "./apps/Schedule";

const root = createRoot(document.getElementById('schedule-root')!);
if (undefined !== root) root.render( <Schedule /> );