/**
 * This file is part of Tak-Me System.
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @author    PlusFive.
 * @copyright (c)2018 PlusFive. (http://www.plus-5.com/)
 */
let calendarHeaders = ['S','M','T','W','T','F','S'];

let calendarCallback = undefined;
let calendarMinDate = undefined;
let calendarMaxDate = undefined;
let calendarWeekDays = undefined;

let calendarContainer = undefined;
const calendarContainerID = 'calendar-ui';

let callbackViaNavi = undefined;
let callbackViaDate = undefined;
let callbackViaDateInactive = undefined;

function removeCalendar(event) {
    if (!calendarContainer) return;

    if (event) {
        const element = event.target;
        if (element.childOf(calendarContainer) !== -1
            || (calendarContainer && calendarContainer.opener === element)
        ) {
            return;
        }
    }

    calendarContainer.parentNode.removeChild(calendarContainer);
    calendarContainer = undefined;
    window.removeEventListener('mouseup', removeCalendar);

    return 1;
}

function popupCalendar(trigger, year, month, days, callback, callbackNavi, callbackInactive) {
    showCalendar('popup', trigger, year, month, days, callback, callbackNavi, callbackInactive); 
}

function pulldownCalendar(trigger, year, month, days, callback, callbackNavi, callbackInactive) {
    showCalendar('pulldown', trigger, year, month, days, callback, callbackNavi, callbackInactive); 
}

function showCalendar(type, trigger, year, month, days, callback, callbackNavi, callbackInactive) {
    if (removeCalendar()) return;

    if (typeof callback === 'function') callbackViaDate = callback;
    if (typeof callbackNavi === 'function') callbackViaNavi = callbackNavi;
    if (typeof callbackInactive === 'function') callbackViaDateInactive = callbackInactive;

    const container = document.body.appendChild(document.createElement('div'));
    container.id = calendarContainerID;
    container.classList.add('calendar-ui-popup');
    container.classList.add('calendar-ui');
    container.opener = trigger;

    const calendar = buildCalendar(year, month, days);
    container.appendChild(calendar);

    const triggerRect = trigger.getBoundingClientRect();
    const containerRect = container.getBoundingClientRect();

    if (type === 'popup') {
        container.style.top = (triggerRect.top + window.pageYOffset - containerRect.height) + 'px';
        container.style.left = (triggerRect.left + Math.round(triggerRect.width / 2) + window.pageXOffset - Math.round(containerRect.width / 2)) + 'px';
    } else if (type === 'pulldown') {
        container.style.top = (triggerRect.top + triggerRect.height + window.pageYOffset) + 'px';
        container.style.left = (triggerRect.left + window.pageXOffset) + 'px';
    }

    calendarContainer = document.getElementById(calendarContainerID);
    window.addEventListener('mouseup', removeCalendar);
}

function buildCalendar(year, month, days) {
    year = parseInt(year);
    month = parseInt(month);

    const lastDay = new Date(year, month, 0);
    const firstDay = new Date(year, month - 1, 1);

    const table = document.createElement('table');
    table.classList.add('dateTimeCalendar');

    const caption = table.appendChild(document.createElement('caption'));
    const inline = caption.appendChild(document.createElement('div'));

    const title = inline.appendChild(document.createElement('span'));
    title.classList.add('current');
    title.innerHTML = year + '-' + month;

    let prevMonth = month -1;
    let prevYear = year;
    if (prevMonth < 1) {
        prevMonth = 12;
        --prevYear;
    }
    const prev = inline.appendChild(document.createElement('a'));
    prev.classList.add('navi');
    prev.classList.add('prev');
    prev.innerHTML = prevMonth;
    prev.href = '#' + prevYear + '-' + prevMonth;
    prev.addEventListener('click', clickNaviButton);

    const now = new Date();
    const today = inline.appendChild(document.createElement('a'));
    today.classList.add('navi');
    today.classList.add('today');
    today.innerHTML = 'Today';
    today.href = '#' + now.getFullYear() + '-' + (now.getMonth() + 1);
    today.addEventListener('click', clickNaviButton);

    let nextMonth = month + 1;
    let nextYear = year;
    if (nextMonth > 12) {
        nextMonth = 1;
        ++nextYear;
    }
    const next = inline.appendChild(document.createElement('a'));
    next.classList.add('navi');
    next.classList.add('next');
    next.innerHTML = nextMonth;
    next.href = '#' + nextYear + '-' + nextMonth;
    next.addEventListener('click', clickNaviButton);

    let minDate = 0;
    if (calendarMinDate) {
        minDate = new Date(calendarMinDate);
    }

    let maxDate = 0;
    if (calendarMaxDate) {
        maxDate = new Date(calendarMaxDate);
    }

    if (!days) {
        days = [];
        let start = (minDate === 0) ? 1 : minDate.getDate();
        let end = (maxDate === 0) ? lastDay.getDate() : maxDate.getDate();
        for (let i = start; i <= end; i++) {
            days.push(i);
        }
    }

    let tr, td, d, start, last, week = 0, row = 6;

    if (calendarHeaders) {
        const thead = table.appendChild(document.createElement('thead'));
        tr = thead.appendChild(document.createElement('tr'));
        for (let i = 0; i < calendarHeaders.length; i++) {
            td = tr.appendChild(document.createElement('td'));
            td.innerHTML = calendarHeaders[i];
        }
    }

    var tbody = table.appendChild(document.createElement('tbody'));
    var enableWeekly = true;
    for (d = 1, last = lastDay.getDate(); d <= last; d++) {
        if (week % 7 === 0) {
            tr = tbody.appendChild(document.createElement('tr'));
            week = 0;
            --row;
        }

        if (d === 1) {
            start = firstDay.getDay();
            while (start > 0) {
                td = tr.appendChild(document.createElement('td'));
                --start;
                ++week;
            }
        }

        if (this.calendarWeekDays) {
            enableWeekly = (this.calendarWeekDays.indexOf(week+'') != -1);
        }

        td = tr.appendChild(document.createElement('td'));
        td.innerHTML = d;
        if (typeof callbackViaDate === 'function') {
            if (days.indexOf(String(d)) !== -1) {
                td.classList.add('clickable');
                td.addEventListener('click', callbackViaDate);
            }
            else {
                td.classList.add('disable');
                if (typeof callbackViaDateInactive === 'function') {
                    td.addEventListener('click', callbackViaDateInactive);
                }
            }
        }
        ++week;
        if (d === last) {
            end = 7 - week;
            while (end > 0) {
                td = tr.appendChild(document.createElement('td'));
                --end;
            }
        }
    }

    while (row > 0) {
        tr = tbody.appendChild(document.createElement('tr'));
        for (var i = 0; i < 7; i++) {
            td = tr.appendChild(document.createElement('td'));
        }
        --row;
    }

    return table;
}

function refreshCalendar(element, year, month, days) {
    var table = buildCalendar(year, month, days);
    element.parentNode.replaceChild(table, element);
}

function clickNaviButton(event) {
    event.preventDefault();

    if (typeof callbackViaNavi === 'function') {
        callbackViaNavi(event);
        return;
    }

    const element = event.currentTarget;
    const dateUnit = element.hash.substr(1).split('-');

    const calendar = element.findParent('table');
    refreshCalendar(calendar, dateUnit[0], dateUnit[1]);
}
