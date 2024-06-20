import { Plugin } from "ckeditor5/src/core";
import EpaAddDefinitionEditing from "./epaadddefinitionediting";
import EpaAddDefinitionUI from "./epaadddefinitionui";

export default class EpaAddDefinition extends Plugin {
  static get requires() {
    return [EpaAddDefinitionUI, EpaAddDefinitionEditing];
  }
}
