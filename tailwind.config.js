/** @type {import('tailwindcss').Config} */
module.exports = {
	content: [
		"./includes/**/*.php",
		'./views/**/*.php',
		"./assets/js/src/**/*.{js,jsx,ts,tsx}'",
	],
	theme: {
		extend: {},
	},
	plugins: [],
	prefix: 'omom-',
}

