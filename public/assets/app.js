/*
 * Optional challenge: collapse / expand reply branches.
 *
 * The server renders every reply expanded, plus a <button hidden> for each comment
 * that has children. This script only REVEALS those buttons and wires them up, so
 * with JavaScript disabled all comments stay visible and no dead controls appear.
 * No comment text is ever read into HTML here (only textContent / attributes).
 */
(function () {
    'use strict';

    document.querySelectorAll('button.toggle-replies').forEach(function (button) {
        var panel = document.getElementById(button.getAttribute('aria-controls'));
        if (!panel) {
            return; // no matching container: leave the button hidden
        }

        button.hidden = false;

        button.addEventListener('click', function () {
            var isExpanded = button.getAttribute('aria-expanded') === 'true';
            panel.hidden = isExpanded;                                   // hide / show only THIS branch
            button.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
            button.textContent = isExpanded ? 'Show replies' : 'Hide replies';
        });
    });
})();
