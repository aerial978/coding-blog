import js from "@eslint/js";
import globals from "globals";
import { defineConfig } from "eslint/config";

export default defineConfig([
  {
    files: ["public/assets/js/**/*.js"],
    plugins: { js },
    extends: ["js/recommended"],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: "module",
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
    rules: {
      "eqeqeq": ["error", "always"],
      "prefer-const": "error",
      "no-var": "error",
      "no-unused-vars": ["error", { argsIgnorePattern: "^_" }],
      "no-implicit-coercion": "error",
      "curly": ["error", "all"],

      "complexity": ["warn", 8],
      "max-depth": ["warn", 3],
      "max-lines-per-function": ["warn", 80],

      "no-nested-ternary": "warn",
      "no-multiple-empty-lines": ["warn", { max: 1, maxEOF: 1 }],
      "no-trailing-spaces": "warn",
      "no-useless-return": "warn",

      "no-console": "off",
    },
  },
]);