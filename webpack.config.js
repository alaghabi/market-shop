const Encore = require('@symfony/webpack-encore');
const webpack = require('webpack');

Encore
  .setOutputPath('public/build/')
  .setPublicPath('/build')
  .addEntry('app', './assets/react/main.tsx')
  .enableStimulusBridge('./assets/controllers.json')
  .enableReactPreset()
  .enableTypeScriptLoader()
  .enablePostCssLoader()
  .enableSingleRuntimeChunk()
  .addPlugin(new webpack.DefinePlugin({
    'process.env.MERCURE_PUBLIC_URL': JSON.stringify(process.env.MERCURE_PUBLIC_URL || ''),
    'process.env.KEYCLOAK_PUBLIC_URL': JSON.stringify(process.env.KEYCLOAK_PUBLIC_URL || ''),
    'process.env.KEYCLOAK_CLIENT_ID': JSON.stringify(process.env.KEYCLOAK_CLIENT_ID || ''),
  }))
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())
  .configureCssMinimizerPlugin((options) => {
    options.minimizerOptions = {
      preset: ['default', { calc: false }],
    };
  })
  .enableVersioning(Encore.isProduction());

module.exports = Encore.getWebpackConfig();
