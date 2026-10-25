// ESLint over the TypeScript and React code (TEST_STRATEGY TL-01; TASK-002).
import js from '@eslint/js';
import reactHooks from 'eslint-plugin-react-hooks';
import { defineConfig, globalIgnores } from 'eslint/config';
import globals from 'globals';
import tseslint from 'typescript-eslint';

import multiplecorp, { rawHtml, surfaceGreys } from './eslint/plugin.js';

export default defineConfig([
    globalIgnores(['bootstrap/', 'node_modules/', 'public/', 'storage/', 'vendor/']),
    js.configs.recommended,
    tseslint.configs.strictTypeChecked,
    reactHooks.configs.flat.recommended,
    {
        languageOptions: {
            globals: globals.browser,
            parserOptions: { projectService: true, tsconfigRootDir: import.meta.dirname },
        },
        plugins: { multiplecorp },
        rules: {
            'no-restricted-syntax': ['error', rawHtml],
            'multiplecorp/surface-grey': ['error', surfaceGreys],
            // LOCALIZATION §3.6: every user-facing string is a key of the catalogue (TASK-014).
            'multiplecorp/hardcoded-copy': 'error',
            // DESIGN_SYSTEM §5: the tokens are the only values (TASK-015).
            'multiplecorp/token-values': 'error',
        },
    },
    {
        files: ['**/*.{js,mjs,cjs}'],
        extends: [tseslint.configs.disableTypeChecked],
        languageOptions: { globals: globals.node },
    },
]);
