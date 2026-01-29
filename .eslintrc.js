module.exports = {
    extends: '../../.eslintrc',
    ignorePatterns: ['amd/src/lib/**/*.js'],
    parser: '@babel/eslint-parser',
    parserOptions: {
        sourceType: 'module',
        requireConfigFile: false,
        babelOptions: {
            configFile: false,
            babelrc: false,
            presets: ['@babel/preset-env']
        }
    },
    env: {
        browser: true,
        es2020: true,
        amd: true
    },
    globals: {
        M: true,
        Y: true,
        Swal: true,
        simpleDatatables: true
    },
    rules: {
        // Relax some rules for plugin code
        'max-len': ['error', {code: 180, ignoreUrls: true, ignoreStrings: true, ignoreTemplateLiterals: true}],
        'jsdoc/require-param': 'off',
        'jsdoc/require-jsdoc': 'off',
        'jsdoc/empty-tags': 'off',
        'no-script-url': 'off',
        'capitalized-comments': 'off',
        'no-multi-spaces': 'off',
        'brace-style': 'off',
        'key-spacing': 'off',
        'comma-spacing': 'off',
        'no-nested-ternary': 'off',
        'no-case-declarations': 'off',
        'camelcase': 'off',
        'curly': 'off',
        'no-return-assign': 'off',
        'promise/always-return': 'off',
        'promise/catch-or-return': 'off',
        'promise/no-nesting': 'off'
    }
};
