# EPA Clone

This custom module provides an event subscriber that allows us to tap into the entity clone event provided by the Entity Clone contrib module.

The main functions of this event subscriber are to reset values on the cloned node so that they do not inherit the various metadata values from the original node.
