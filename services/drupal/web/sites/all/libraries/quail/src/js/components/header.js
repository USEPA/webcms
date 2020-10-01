quail.components.header = function(testName, options) {
  var headingLevel = parseInt(options.selector.substr(-1, 1), 10);
  var priorLevel = false;
  quail.html.find(':header').each(function() {
    var level = parseInt($(this).get(0).tagName.substr(-1, 1), 10);
    if(priorLevel && level === headingLevel && (level > priorLevel + 1)) {
      quail.testFails(testName, $(this));
    }
    priorLevel = level;
  });
};
