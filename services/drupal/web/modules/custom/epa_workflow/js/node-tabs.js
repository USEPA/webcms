/* 
*
* click to expand node tabs
*
*/

var nodeLinkChildren = document.querySelector('.node-tabs__item.has-children');

nodeLinkChildren.onclick = function() {
  this.classList.toggle('is-selected');
}
