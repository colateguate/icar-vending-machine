import ProductRow from './ProductRow';

/**
 * The Products half of the service form: the catalogue as rows a technician can
 * edit, retire and add to. Split out of the drawer when the drawer grew tabs —
 * each half is a panel now, and a panel is a component.
 *
 * It renders a fieldset rather than receiving one, so the `disabled` lock
 * arrives as a prop and applies to everything inside at once, the add button
 * included: a row added to a form that is mid-flight would be a row the answer
 * knows nothing about.
 */
export default function ShelfPanel({ products, problems, disabled, onChange, onAdd, onRemove }) {
  return (
    <fieldset className="service__group" disabled={disabled}>
      <legend className="visually-hidden">Products</legend>
      <ul className="service__rows">
        {products.map((product) => (
          <ProductRow
            key={product.id}
            onChange={(change) => onChange(product.id, change)}
            onRemove={() => onRemove(product.id)}
            problems={problems[product.id]}
            product={product}
          />
        ))}
      </ul>

      <button className="service__add" onClick={onAdd} type="button">
        Add product
      </button>
    </fieldset>
  );
}
