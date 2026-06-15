const path = require('path')
const nodeModulesPath = path.resolve(__dirname, 'node_modules');
const entryPoints = {
    main: "./src/front.js",
    styles: "./src/scss/front.scss",
};

const CopyWebpackPlugin = require("copy-webpack-plugin");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const TerserPlugin = require("terser-webpack-plugin");
const { WebpackAssetsManifest } = require('webpack-assets-manifest');

module.exports = {
    mode: 'development',
    entry: entryPoints,
    plugins: [
        new MiniCssExtractPlugin({
            filename: "[name].[contenthash].css",
            chunkFilename: "[id].css",
        }),
        new WebpackAssetsManifest({
            output: 'assets-manifest.json',
            publicPath: true,
            writeToDisk: true,
        }),
        /*new CopyWebpackPlugin({
            patterns: [
                {
                    from: path.resolve(__dirname, 'src/assets'),
                    to: path.resolve(__dirname, 'dist/assets')
                }
            ]
        })*/
    ],
    output: {
        filename: 'bundle.[contenthash].js',
        path: path.resolve(__dirname, 'dist'),
        clean: true,
    },
    module: {
        rules: [
            {
                test: /\.s[ac]ss$/i,
                use: [
                    MiniCssExtractPlugin.loader,
                    {
                        loader: 'css-loader',
                    },
                    {
                        loader: "sass-loader",
                        options: {
                            sassOptions: {
                                includePaths: [nodeModulesPath],
                            },
                        },
                    },
                ],
            },
            /*{
                test: /\.(woff2?|eot|ttf|otf)$/i,
                type: 'asset/resource',
                generator: {
                    filename: 'assets/fonts/[name][ext][query]',
                },
            },
            {
                test: /\.(png|jpe?g|gif|svg|webp|ico)$/i,
                type: 'asset/resource',
                generator: {
                    filename: 'assets/images/[name][ext][query]',
                },
            },*/
        ],
    },
    optimization: {
        minimize: true,
        minimizer: [new TerserPlugin()],
        splitChunks: {
            cacheGroups: {
                styles: {
                    type: 'css/mini-extract',
                },
            },
        },
    },
    watchOptions: {
        poll: 1000,
    },
};