import type { Configuration } from 'lint-staged';
import path from 'node:path';
import process from 'node:process';

const prettier = (filenames: readonly string[]) => {
    return filenames.map((filename) => {
        return [
            'prettier --write',
            '--config',
            path.join(process.cwd(), '.prettierrc'),
            filename,
        ].join(' ');
    });
};

const eslint = (filenames: readonly string[]) => {
    return filenames.map((filename) => {
        return [
            'eslint --fix',
            '--config',
            path.join(process.cwd(), 'eslint.config.js'),
            filename,
        ].join(' ');
    });
};

const pint =
    (config = 'pint.json') =>
    (filenames: readonly string[]) => {
        return filenames.map((filename) => {
            return [
                path.join(process.cwd(), 'vendor/bin/pint'),
                '--config',
                path.join(process.cwd(), config),
                filename,
            ].join(' ');
        });
    };

const test = () => 'php artisan test --parallel';

export default {
    '(app|bootstrap|helpers|routes|tests)/**/*.php': [pint()],
    'database/(factories|seeders)/**/*.php': [pint()],
    'database/migrations/*.php': [pint('storage/pint/migrations.json')],
    'config/*.php': [pint('storage/pint/config.json')],
    'resources/**/*.{js,jsx,ts,tsx,vue}': [prettier, eslint],
    'resources/**/*.{json,jsonc}': [prettier],
    '(app|bootstrap|config|database|helpers|resources|routes|tests)': [test],
    '.github/**/*.{yaml,yml}': [prettier],
    './*.{js,mjs,ts,json,jsonc,yaml,yml}': [prettier, eslint],
} satisfies Configuration;
