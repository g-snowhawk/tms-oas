{% extends "master.tpl" %}

{% block main %}
  <div class="wrapper">
    <h1>帳簿一覧</h1>
    <ul class="button-list">
      <li><a href="?mode=oas.transfer.response&amp;t=T">振替伝票</a></li>
      <li><a href="?mode=oas.transfer.response&amp;t=R">入金伝票</a></li>
      <li><a href="?mode=oas.transfer.response&amp;t=P">出金伝票</a></li>
    </ul>
  </div>
{% endblock %}
