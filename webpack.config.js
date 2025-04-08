const path = require("path");
const defaults = require('@wordpress/scripts/config/webpack.config.js');

module.exports = {

	...defaults,

	// bundling mode
	mode: 'production', // production or development

	// entry files
	entry: {
        arrival: "./assets/js/src/bikeBuilderArrivalDate.ts",
        post: "./assets/js/src/postPage.tsx",
        schedule: "./assets/js/src/schedulePage.tsx",
		settings: "./assets/js/src/settingsPage.tsx"
	},

	// output bundles (location)
	output: {
		filename: "omom-[name].js",
		path: path.resolve(__dirname, "assets/js/dist"),
		clean: true,
	},

	// Enable watch mode
	watch: true, 
	watchOptions: {
		aggregateTimeout: 200,
		ignored: '**/node_modules',
	},

	// file resolutions
	resolve: {
		extensions: [ 
			'.ts', 
			'.tsx', 
			...(defaults.resolve ? defaults.resolve.extensions || ['.js', '.jsx'] : [])
		],
		alias: {
			'@': path.resolve(__dirname, 'assets/js/src')
		}
	},

	// file resolutions
	module: {
		...defaults.module,
		rules: [
			...defaults.module.rules,
			{
				test: /\.js$/,
				use: ["source-map-loader"],
				enforce: "pre",
			},
			{
				test: /\.css$/i,
				use: [
					"style-loader", 
					"css-loader",
					{
                        loader: 'postcss-loader',
                        options: {
                            postcssOptions: {
                                plugins: [
                                    require('tailwindcss'),
                                    require('autoprefixer'),
                                ],
                            },
                        },
                    },
				],
			},
			{
				test: /\.tsx?$/,
				use: "ts-loader",
				exclude: /node_modules/
			},
		],
	},

	// devtool: "inline-source-map",

	externals: [
		{jquery: 'jQuery'},
		{$     : 'jQuery'}
	],
};