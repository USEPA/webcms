import { View } from "ckeditor5/src/ui";
<<<<<<< HEAD
=======

>>>>>>> 28c9316ee (EPAD8-1930: Add definitions plugin)
import MatchView from "./matchview";
import reconcileViews from "./reconcileViews";

export default class MatchListView extends View {
  constructor(locale) {
    super(locale);

    this.set("matches", []);

    this.views = this.createCollection();

    this.setTemplate({
      tag: "div",
      children: this.views,
    });

    this.on("change:matches", (_event, _property, data) => {
      reconcileViews(
        data,
        this.views,
        () => new MatchView(this.locale),
        (view, datum) => {
          view.term = datum.term;
          view.definitions = datum.definitions;
        }
      );
    });
  }
}
