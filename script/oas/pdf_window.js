/**
 * This file is part of Tak-Me System.
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @author    PlusFive.
 * @copyright (c)2020 PlusFive. (http://www.plus-5.com/)
 */

switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', initializePdfWindow)
        break;
    case 'interactive':
    case 'complete':
        initializeTransferEditor();
        break;
}

function initializePdfWindow(event) {
    for (var i = 0; i < document.forms.length; i++) {
        let form = document.forms[i];
        if (form.target == "TmsPDFWindow") {
            form.addEventListener("submit", openPdfWindow);
        }
    }
}

function openPdfWindow(event) {
    event.preventDefault();
    let form = event.target;
    window.open("about:blank", form.target);
    form.submit();
}
