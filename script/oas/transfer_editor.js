/**
 * This file is part of Tak-Me Online Accounting System.
 *
 * Copyright (c)2019 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */
'use strict';

const debugLevel = 0;

const formatter = new Intl.NumberFormat('ja-JP');
const viewMode = 'oas.transfer.response';
const editMode = 'oas.transfer.response:edit';
const calendarMode = 'oas.transfer.response:calendar';
const mainFormID = 'TMS-mainform';
let locationHash = location.hash;
let inputIssueDate = undefined;
let inputAmountLeft = undefined;
let inputAmountRight = undefined;
let selectItemCodeLeft = undefined;
let selectItemCodeRight = undefined;
let displayTotalLeft = undefined;
let displayTotalRight = undefined;
let itemCodeTemplate = undefined;
let buttonAddPage = undefined;
let buttonUnlock = undefined;
let buttonCancel = undefined;
let linkPreviousPage = undefined;
let linkNextPage = undefined;
let token = undefined;
let issueDate = undefined;
let pageNumber = undefined;
let naviPagination = undefined;

switch (document.readyState) {
    case 'loading' :
        window.addEventListener('DOMContentLoaded', initializeTransferEditor)
        break;
    case 'interactive':
    case 'complete':
        initializeTransferEditor();
        break;
}

function initializeTransferEditor(event) {
    token = document.querySelector('input[name=stub]').value;

    if (locationHash) {
        moveToPage();
        locationHash = undefined;
        return;
    }

    inputIssueDate = document.querySelector('input[name=issue_date]');
    inputAmountLeft = document.querySelectorAll('input[name^=amount_left]');
    inputAmountRight = document.querySelectorAll('input[name^=amount_right]');
    selectItemCodeLeft = document.querySelectorAll('select[name^=item_code_left]');
    selectItemCodeRight = document.querySelectorAll('select[name^=item_code_right]');
    displayTotalLeft = document.getElementById('total-left');
    displayTotalRight = document.getElementById('total-right');
    itemCodeTemplate = document.getElementById('account-items');
    buttonAddPage = document.getElementById('addpage');
    buttonUnlock = document.getElementById('unlock');
    buttonCancel = document.getElementById('cancel');
    naviPagination = document.getElementById('page-nav');
    linkPreviousPage = document.getElementById('prev-page-link');
    linkNextPage = document.getElementById('next-page-link');

    issueDate = document.querySelector('input[name=issue_date]').value;
    pageNumber = document.querySelector('input[name=page_number]').value;

    const currentForm = document.getElementById(mainFormID);
    currentForm.addEventListener('submit', checkTransferBeforeSubmit);

    lockForm();

    let i;
    for (i = 0; i < inputAmountLeft.length; i++) {
        inputAmountLeft[i].addEventListener('keyup', culculateTotals);
    }
    culculateTotals(event);
    for (i = 0; i < inputAmountRight.length; i++) {
        inputAmountRight[i].addEventListener('keyup', culculateTotals);
    }
    culculateTotals(event, 'right');

    for (i = 0; i < selectItemCodeLeft.length; i++) {
        let element = selectItemCodeLeft[i];
        element.addEventListener('focus', focusSelectElement);
        appendItemCodeOptions(element);
    }
    for (i = 0; i < selectItemCodeRight.length; i++) {
        let element = selectItemCodeRight[i];
        element.addEventListener('focus', focusSelectElement);
        appendItemCodeOptions(element);
    }

    if (buttonAddPage) buttonAddPage.addEventListener('click', addNewPage);
    if (buttonUnlock) buttonUnlock.addEventListener('click', unlockForm);
    if (buttonCancel) buttonCancel.addEventListener('click', backToViewMode);
    if (linkPreviousPage) linkPreviousPage.addEventListener('click', moveToPage);
    if (linkNextPage) linkNextPage.addEventListener('click', moveToPage);

    const calendarSearch = document.getElementById('calendar-search');
    calendarSearch.addEventListener('click', openCalendarForSearch);

    // TODO: avoid dependencies
    if (TM.form) TM.form.through = true;
}

function openCalendarForSearch(event) {
    event.preventDefault();

    let queryString = '?mode=' + calendarMode + '&date=' + inputIssueDate.value;
    fetch(location.pathname + queryString, {
        method: 'GET',
        credentials: 'same-origin'
    })
        .then(function(response){
            response.json().then(function(result){
                const date = new Date(result.date);
                const days = result.days || null;
                const element = event.currentTarget || event.target;

                popupCalendar(
                    element,
                    date.getFullYear(), date.getMonth() + 1, days,
                    moveToPage, moveCalendar, addNewPage
                );
            })
        })
        .catch(error => console.error(error));
}

function hashFromCalendar(element) {
    const table = element.findParent('table');
    const current = table.querySelector('.current').innerHTML;
    return '#' + current + '-' + element.innerHTML;
}

function moveCalendar(event) {
    event.preventDefault();

    const element = event.currentTarget;
    let queryString = '?mode=' + calendarMode + '&date=' + element.hash.substr(1) + '-01';
    fetch(location.pathname + queryString, {
        method: 'GET',
        credentials: 'same-origin'
    })
        .then(function(response){
            response.json().then(function(result){
                const date = new Date(result.date);
                const days = result.days || null;
                const element = event.currentTarget || event.target;

                const container = element.findParent('table');
                refreshCalendar(container, date.getFullYear(), date.getMonth() + 1, days);
            })
        })
        .catch(error => console.error(error));
}

function focusSelectElement(event)
{
    appendItemCodeOptions(event.currentTarget);
}

function appendItemCodeOptions(element)
{
    if (!itemCodeTemplate || element.options.length > 0) return;

    const clone = document.importNode(itemCodeTemplate.content, true);
    element.appendChild(clone);

    if (element.dataset.defaultValue !== '') {
        let i;
        for (i = 0; i < element.options.length; i++) {
            const option = element.options[i];
            if (option.value === element.dataset.defaultValue) {
                option.selected = true;
                break;
            }
        }
    }
}

function culculateTotals(event, leftOrRight) {
    let amountItems = inputAmountLeft;
    let displayTotal = displayTotalLeft;

    if (event && event.currentTarget) {
        if (event.currentTarget.name.indexOf('amount_right') === 0) {
            leftOrRight = 'right';
        }
    }

    if (leftOrRight === 'right') {
        amountItems = inputAmountRight;
        displayTotal = displayTotalRight;
    }

    if (displayTotal === null) return;

    let total = 0;
    let i;
    for (i = 0; i < amountItems.length; i++) {
        let amount = parseInt(amountItems[i].value.replace(/,/g, ''));
        if (isNaN(amount)) amount = 0;

        total += amount;
    }

    displayTotal.innerHTML = (total === 0) ? '' : formatter.format(total);
}

/*
function hideSuggestionList(event) {
    const element = event.target;
    if (element === inputCompany
        || element.childOf(suggestionListContainer) !== -1
    ) {
        return;
    }

    suggestionListLock = false;
    displaySuggestionList('');
}
*/

/*
function displaySuggestionList(source) {

    if (debugLevel > 0) {
        console.log(source);
        console.log(suggestionListLock);
    }

    if (!suggestionListContainer) return;

    let list = document.getElementById(suggestionListID);
    if (list && !suggestionListLock) {
        list.parentNode.removeChild(list);
        window.removeEventListener('mouseup', hideSuggestionList);
    }
    if (source === '') {
        return;
    }

    list = suggestionListContainer.appendChild(document.createElement('div'));
    list.id = suggestionListID;
    list.innerHTML = source;

    let i;
    let anchors = list.getElementsByTagName('a');
    for (i = 0; i < anchors.length; i++) {
        anchors[i].addEventListener('mousedown', switchSuggestionListLock);
        anchors[i].addEventListener('mouseup', switchSuggestionListLock);
    }

    window.addEventListener('mouseup', hideSuggestionList);
}
*/

/*
function suggestComplete(response) {
    response.json().then(function(data){

        if (debugLevel > 0) {
            console.log(data);
        }

        if (data.status === 0) {
            displaySuggestionList(data.source);
        }
    }).catch(error => console.error(error));
}
*/

/*
function suggestClient() {
    if (inputCompany.value === '') {
        displaySuggestionList('');
        return;
    }

    if (debugLevel > 0) {
        console.log(inputCompany.value);
    }

    const form = inputCompany.form;

    let data = new FormData();
    data.append('stub', form.stub.value);
    data.append('keyword', inputCompany.value);
    data.append('mode', 'srm.receipt.receive:suggest-client');

    suggestionBrowser.abort();
    const signal = suggestionBrowser.signal;

    fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        body: data,
    }).then(
        suggestComplete
    ).catch(error => console.error(error));
}
*/

/*
function switchComposing(event) {
    isComposing = (event.type === 'compostionstart');
}
*/

/*
function switchSuggestionListLock(event) {
    suggestionListLock = (event.type === 'mousedown');;
}
*/

/*
function listenerForSuggestion(event) {

    if (debugLevel > 0) {
        console.log(event.type);
    }

    let inputedValue = event.target.value;
    switch (event.type) {
        case 'blur':
            displaySuggestionList('');
            break;
        case 'keydown':
            valueAtKeyDown = inputedValue;
            break;
        case 'keyup':
            if (event.key === 'ArrowDown'
                || (!isComposing && valueAtKeyDown !== inputedValue)
            ) {
                clearTimeout(suggestionTimer);
                suggestionTimer = setTimeout(suggestClient, suggestionTimeout);
            }

            else {
                if (debugLevel > 0) {
                    console.log(event.key);
                }
            }

            valueAtKeyDown = undefined;
            break;
    }
}
*/

function addNewPage(event) {
    const element = event.currentTarget;

    let queryString = '?mode=' + editMode + '&add=1';

    if (element.findParent('.calendar-ui')) {
        const hash = hashFromCalendar(element);
        queryString += '&issue_date=' + hash.substr(1);
        removeCalendar();
    } else if (linkNextPage) {
        queryString += '&issue_date=' + issueDate;
    }

    fetch(location.pathname + queryString, {
        method: 'GET',
        credentials: 'same-origin'
    }).then(response => response.text())
    .then(source => replaceForm(source))
    .catch(error => console.error(error));
}

function reloadTransferPage(date, page) {
    let data = new FormData();
    data.append('stub', token);
    data.append('mode', viewMode);
    data.append('cur', date);
    if (page) data.append('p', page);

    fetch(location.pathname, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
    }).then(response => response.text())
    .then(source => replaceForm(source))
    .catch(error => console.error(error));
}

function backToViewMode(event) {
    reloadTransferPage(issueDate, pageNumber);
}

function lockForm() {
    const forms = document.querySelectorAll('form');
    for (let form of forms) {
        if (form.classList.contains('readonly')) {
            let i;
            for (i = 0; i < form.elements.length; i++) {
                let element = form.elements[i];
                if (element.dataset.lockType !== 'never') {
                    element.disabled = true;
                }
            }
        } else {
            if (naviPagination && naviPagination.findParent('form') === form) {
                naviPagination.classList.add('hidden-block');
            }
        }
    }
}

function unlockForm() {
    const forms = document.querySelectorAll('form.readonly');
    for (let form of forms) {
        if (form.classList.contains('readonly')) {
            let i;
            for (i = 0; i < form.elements.length; i++) {
                let element = form.elements[i];
                if (element.dataset.lockType !== 'ever') {
                    element.disabled = false;
                }
            }

            const button = buttonUnlock || buttonAddPage;
            if (form === button.form) {
                button.parentNode.classList.add('hidden-block');
            }

            if (naviPagination) {
                naviPagination.classList.add('hidden-block');
            }
        }
    }
}

function moveToPage(event) {
    let hash = location.hash;
    if (event) {
        event.preventDefault();
        const element = event.currentTarget;
        if (element.hash) {
            hash = element.hash;
        } else if (element.findParent('.calendar-ui')) {
            hash = hashFromCalendar(element);
            removeCalendar();
        }
    }

    const dateAndPage = hash.substr(1).split(':');
    reloadTransferPage(dateAndPage[0], dateAndPage[1]);
}

function replaceForm(source) {
    const template = document.createElement('template');
    template.innerHTML = source;

    const newForm = template.content.querySelector('#' + mainFormID);
    const currentForm = document.getElementById(mainFormID);

    if (newForm && currentForm) {
        currentForm.parentNode.replaceChild(newForm, currentForm);
        initializeTransferEditor();
    }
}

function checkTransferBeforeSubmit(event)
{
    const form = event.currentTarget;
    if (displayTotalLeft
        && displayTotalRight
        && displayTotalLeft.innerHTML !== displayTotalRight.innerHTML
    ) {
        event.preventDefault();
        alert(displayTotalRight.dataset.message);
        return;
    }

    const elements = form.querySelectorAll('*[data-lock-type=ever]');
    for (let element of elements) {
        element.disabled = false;
    }
}
