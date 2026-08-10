/**
 * Tiny synchronous form-validation helper shared by all create/edit forms.
 *
 * A rule is a function that returns an error message string when the value is
 * invalid, or null when it's valid. `validate` runs a map of field → rules and
 * returns a map of field → first error message (only for fields that failed).
 */
export type Rule = (value: unknown) => string | null;

export type RuleSet = Record<string, Rule[]>;

export type Errors = Record<string, string>;

function isEmpty(value: unknown): boolean {
    return value === null || value === undefined || String(value).trim() === "";
}

export const required = (message = "This field is required."): Rule =>
    (value) => (isEmpty(value) ? message : null);

export const email = (message = "Enter a valid email address."): Rule =>
    (value) => {
        if (isEmpty(value)) return null;
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value)) ? null : message;
    };

export const minLength = (min: number, message?: string): Rule =>
    (value) => {
        if (isEmpty(value)) return null;
        return String(value).length >= min
            ? null
            : message ?? `Must be at least ${min} characters.`;
    };

export const maxLength = (max: number, message?: string): Rule =>
    (value) => {
        if (isEmpty(value)) return null;
        return String(value).length <= max
            ? null
            : message ?? `Must be at most ${max} characters.`;
    };

export const pattern = (regex: RegExp, message: string): Rule =>
    (value) => {
        if (isEmpty(value)) return null;
        return regex.test(String(value)) ? null : message;
    };

export const url = (message = "Enter a valid URL (including https://)."): Rule =>
    (value) => {
        if (isEmpty(value)) return null;
        try {
            // eslint-disable-next-line no-new
            new URL(String(value));
            return null;
        } catch {
            return message;
        }
    };

export const numeric = (message = "Enter a valid number."): Rule =>
    (value) => {
        if (isEmpty(value)) return null;
        return isFinite(Number(value)) ? null : message;
    };

export const min = (minValue: number, message?: string): Rule =>
    (value) => {
        if (isEmpty(value)) return null;
        return Number(value) >= minValue
            ? null
            : message ?? `Must be at least ${minValue}.`;
    };

/**
 * Run a rule set against a values object and return a map of the first error
 * per failing field. An empty object means the form is valid.
 */
export function validate(values: Record<string, unknown>, rules: RuleSet): Errors {
    const errors: Errors = {};

    for (const field in rules) {
        for (const rule of rules[field]) {
            const message = rule(values[field]);
            if (message) {
                errors[field] = message;
                break;
            }
        }
    }

    return errors;
}
