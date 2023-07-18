let nextId = 0;

function createId() {
  return `epa-add-def-${nextId++}`;
}

export default createId;
