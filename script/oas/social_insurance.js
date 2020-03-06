/**
 * This file is part of Tak-Me System.
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @author    PlusFive.
 * @copyright (c)2020 PlusFive. (http://www.plus-5.com/)
 */
const DICTIONARY = {
    "ja-JP" : {
        Cancel : '\u30AD\u30E3\u30F3\u30BB\u30EB',
    },
};

String.prototype.translate = function(dict) {
    const lang = (navigator.languages && navigator.languages[0]) || navigator.language;
    if (dict[lang] && dict[lang][this]) {
        return dict[lang][this].replace(/(\\u)([0-9A-F]{4})/g, function(match,p1,p2){
            return String.fromCharCode(parseInt(p2, 16));
        });
    }
    return this;
}

switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', initializeSocialinsurance)
        break;
    case 'interactive':
    case 'complete':
        initializeTransferEditor();
        break;
}

function initializeSocialinsurance(event) {
    document.querySelectorAll("a.remove").forEach((element) => {
        element.addEventListener('click', postRemoveSocialinsurance);
    });

    let table = document.querySelector("table.data-list");
    table.querySelectorAll('tr').forEach((element) => {
        element.addEventListener('click', editSocialinsurance);
    });
}

let cancelEditSocialinsuranceButton;

function editSocialinsurance(event) {
    let element = event.currentTarget;
    let detail = JSON.parse(element.dataset.detail);

    let form = document.getElementById('TMS-mainform');
    form.title.value = detail['title'];
    form.amount.value = detail['amount'];

    for (let name of ['year', 'colnumber']) {
        let options = form[name].options;
        for (let i = 0; i < options.length; i++) {
            if (options[i].value === detail[name]) {
                form[name].selectedIndex = i;
                break;
            }
        }
    }

    let button = form.s1_submit;
    if (cancelEditSocialinsuranceButton) {
        cancelEditSocialinsuranceButton.parentNode.removeChild(cancelEditSocialinsuranceButton);
        cancelEditSocialinsuranceButton = undefined;
    }
    cancelEditSocialinsuranceButton = document.createElement('input');
    cancelEditSocialinsuranceButton.type = 'reset';
    cancelEditSocialinsuranceButton.value = 'Cancel'.translate(DICTIONARY);
    button.parentNode.appendChild(cancelEditSocialinsuranceButton);
    cancelEditSocialinsuranceButton.addEventListener('click', cancelEditSocialinsurance);
}

function cancelEditSocialinsurance(event) {
    event.preventDefault();
    let form = document.getElementById('TMS-mainform');
    form.title.value = '';
    form.amount.value = '';
    for (let name of ['year', 'colnumber']) {
        form[name].selectedIndex = null;
    }

    if (cancelEditSocialinsuranceButton) {
        cancelEditSocialinsuranceButton.parentNode.removeChild(cancelEditSocialinsuranceButton);
        cancelEditSocialinsuranceButton = undefined;
    }
}

function postRemoveSocialinsurance(event) {
    event.preventDefault();
    event.stopPropagation();
    let element = event.currentTarget;
    if (!confirm(element.dataset.confirmation)) {
        return;
    }

    let form = document.getElementById('TMS-mainform');
    form.mode.value = form.mode.value.replace(/^(.+):.+$/, '$1:remove');
    let input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'id';
    input.value = element.hash.match(/^#remove:([0-9]+)$/)[1];
    form.appendChild(input);
    form.submit();
}
