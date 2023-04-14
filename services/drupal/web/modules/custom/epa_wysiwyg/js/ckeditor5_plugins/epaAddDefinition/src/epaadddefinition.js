import { Plugin } from "ckeditor5/src/core";
<<<<<<< HEAD
=======

>>>>>>> 28c9316ee (EPAD8-1930: Add definitions plugin)
import EpaAddDefinitionEditing from "./epaadddefinitionediting";
import EpaAddDefinitionUI from "./epaadddefinitionui";

export default class EpaAddDefinition extends Plugin {
  static get requires() {
    return [EpaAddDefinitionUI, EpaAddDefinitionEditing];
  }
}
