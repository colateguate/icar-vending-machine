/**
 * RFC 7807 problem documents, as this API writes them.
 *
 * The five required members are the contract; anything else in the document is
 * an extension that only some failures carry (`missingAmount`, `changeDue`,
 * `field`). Extensions are kept in their own bag rather than spread onto the
 * error, so a problem that grows a new member cannot quietly shadow `status`
 * or `code`, and so a caller reading `problem.extensions` knows it is reading
 * something optional.
 */
const isString = (value) => typeof value === 'string';

export function isProblemDocument(value) {
  if (value === null || typeof value !== 'object' || Array.isArray(value)) {
    return false;
  }

  return (
    isString(value.type) &&
    isString(value.title) &&
    typeof value.status === 'number' &&
    isString(value.detail) &&
    isString(value.code)
  );
}

/**
 * The machine refused, and said why in the contract's own terms.
 *
 * Branch on `code`: it is the stable name of the failure (ADR-0012). `detail`
 * is English written for a person and may be reworded without warning, so a
 * panel that switches on it breaks on a copy edit.
 */
export class ApiProblem extends Error {
  constructor({ type, title, status, detail, code, extensions }) {
    super(detail);

    this.name = 'ApiProblem';
    this.type = type;
    this.title = title;
    this.status = status;
    this.detail = detail;
    this.code = code;
    this.extensions = extensions;
  }

  static from(document) {
    const { type, title, status, detail, code, ...extensions } = document;

    return new ApiProblem({ type, title, status, detail, code, extensions });
  }
}
