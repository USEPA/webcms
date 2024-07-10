/**
 * @template T
 * @template {import('@ckeditor/ckeditor5-ui').View} V
 *
 * @param {T[]} data
 * @param {import('@ckeditor/ckeditor5-ui').ViewCollection<V>} views
 * @param {() => V} createView
 * @param {(view: V, data: T) => void} updateView
 */
function reconcileViews(data, views, createView, updateView) {
  const dataLength = data.length;
  const viewLength = views.length;

  const sharedLength = Math.min(dataLength, viewLength);

  for (let i = 0; i < sharedLength; i++) {
    updateView(views.get(i), data[i]);
  }

  if (dataLength > viewLength) {
    const toAdd = data.slice(sharedLength).map((datum) => {
      const view = createView();
      updateView(view, datum);
      return view;
    });

    views.addMany(toAdd);
  } else if (dataLength < viewLength) {
    const toRemove = views.filter((_, i) => i >= sharedLength).reverse();

    for (const view of toRemove) {
      views.remove(view);
    }
  }
}

export default reconcileViews;
