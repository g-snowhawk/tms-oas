{% extends "master.tpl" %}

{% block head %}
  <link rel="stylesheet" href="/style/oas/calendar.css">
  {% for item in accountItems %}
    {% if loop.first %}
      <template id="account-items">
        <option value=""></option>
    {% endif %}
    {% set itemName = item.alias is empty ? item.item_name : item.alias %}
    {% if itemName is not empty %}
      <option value="{{ item.item_code }}">{{ itemName }}{% if item.note is not empty %} ({{ item.note }}){% endif %}</option>
    {% endif %}
    {% if loop.last %}
      </template>
    {% endif %}
  {% endfor %}
{% endblock %}

{% block main %}
  {% set h1 = {'T': '振替', 'P': '出金', 'R': '入金'} %}
  {% set class = {'T': 'transfer', 'P': 'payment', 'R': 'receipt'} %}
  <input type="hidden" name="mode" value="oas.transfer.receive:save">
  <input type="hidden" name="category" value="{{ post.category }}">
  <p id="backlink"><a href="?mode=oas.transfer.response">帳簿一覧</a></p>
  <div class="wrapper">
    <h1>{{ h1[post.category] }}伝票</h1>
    <div class="transfer-form">
      <table class="transfer-detail {{ class[post.category] }}">
        <thead>
          <tr class="header">
          {% if post.category == 'T' %}
          <td colspan="5">{% include 'oas/transfer/meta_data.tpl' %}</td>
          {% else %}
            <td colspan="3">
              <div class="flex-box">
                {% include 'oas/transfer/meta_data.tpl' %}
                <div>
                  <input type="text" name="trade" value="{{ post.trade }}" placeholder="{{ h1[post.category] }}先">
                </div>
              </div>
            </td>
          {% endif %}
          </tr>
          <tr>
          {% if post.category == 'T' %}
            <td>金額</td>
            <td>借方科目</td>
            <td>摘要</td>
            <td>貸方科目</td>
            <td>金額</td>
          {% else %}
            <td>勘定科目</td>
            <td>摘要</td>
            <td>金額</td>
          {% endif %}
          </tr>
        </thead>
        <tfoot>
          <tr>
          {% if post.category == 'T' %}
            <td class="value" id="total-left"></td>
            <td colspan="3"><div class="label">合計</div></td>
            <td class="value" id="total-right" data-message="借方と貸方の合計が一致しません"></td>
          {% else %}
            <td colspan="2"><div class="label">合計</div></td>
            <td class="value" id="total-{% if post.category == 'P' %}left{% else %}right{% endif %}"></td>
          {% endif %}
          </tr>
          <tr id="page-nav">
            <td colspan="{% if post.category == 'T' %}5{% else %}3{% endif %}">
              <a href="#calender" id="calendar-search" class="calendar-trigger">日付で検索</a>
            {% if prevPage is not empty %}
              <a href="#{{ prevPage.issue_date|url_encode }}:{{ prevPage.page_number|url_encode }}" id="prev-page-link">&lt;</a>
            {% else %}
              <span>&lt;</span>
            {% endif %}
            {% if nextPage is not empty %}
              <a href="#{{ nextPage.issue_date|url_encode }}:{{ nextPage.page_number|url_encode }}" id="next-page-link">&gt;</a>
            {% else %}
              <span>&gt;</span>
            {% endif %}
            </td>
          </tr>
        </tfoot>
        <tbody>
          {% for i in 1..lineCount %}
            {% set attr = i == 1 ? ' required' : '' %}
            <tr>
            {% if post.category == 'R' %}
              <td><select name="item_code_right[{{ i }}]" data-default-value="{{ post.item_code_right[i] }}"></select></td>
              <td><input type="text" name="summary[{{ i }}]" value="{{ post.summary[i] }}"></td>
              <td><input type="number" name="amount_right[{{ i }}]" value="{{ post.amount_right[i] }}"{{ attr }}></td>
            {% elseif post.category == 'P' %}
              <td><select name="item_code_left[{{ i }}]" data-default-value="{{ post.item_code_left[i] }}"></select></td>
              <td><input type="text" name="summary[{{ i }}]" value="{{ post.summary[i] }}"></td>
              <td><input type="number" name="amount_left[{{ i }}]" value="{{ post.amount_left[i] }}"{{ attr }}></td>
            {% else %}
              <td><input type="number" name="amount_left[{{ i }}]" value="{{ post.amount_left[i] }}"{{ attr }}></td>
              <td><select name="item_code_left[{{ i }}]" data-default-value="{{ post.item_code_left[i] }}"></select></td>
              <td><input type="text" name="summary[{{ i }}]" value="{{ post.summary[i] }}"></td>
              <td><select name="item_code_right[{{ i }}]" data-default-value="{{ post.item_code_right[i] }}"></select></td>
              <td><input type="number" name="amount_right[{{ i }}]" value="{{ post.amount_right[i] }}"{{ attr }}></td>
            {% endif %}
            </tr>
          {% endfor %}
        </tbody>
      </table>

      {% if pageCount > 1 %}
      <nav class="page-nav">
        {% for i in range(1, pageCount) %}
        <a href="?mode=srm.receipt.response:edit&id={{ post.issue_date }}:{{ post.receipt_number }}:{{ i }}">{{ i }}</a>
        {% endfor %}
      </nav>
      {% endif %}

    </div>
    <div class="form-footer">
        <div class="separate-block">
          <span>
            <input type="reset" id="cancel" value="キャンセル">
            <input type="submit" name="s1_submit" value="保存">
          </span>
          <span>
            {% if readonly == true and post.locked != '1' %}
            <input type="button" id="unlock" value="編集" data-lock-type="never">
            {% endif %}
            {% if post.addnew != '1' %}
            <input type="button" id="addpage" value="次葉の追加" data-lock-type="never">
            {% endif %}
          </span>
        </div>
    </div>
  </div>
</form>
<form action="{{ form.action }}" method="{{ form.method }}" enctype="{{ form.enctype }}" target="TmsPDFWindow">
  <input type="hidden" name="stub" value="{{ stub }}">
  <input type="hidden" name="mode" value="oas.transfer.response:pdf">
  <input type="hidden" name="category" value="{{ post.category }}">
  <div class="wrapper">
    <hr>
    <p class="ta-c date-range">
      <label>出力期間</label>
      <input type="date" name="begin" required><span>〜</span><input type="date" name="end" required>
    </p>
    <p class="ta-r">
      <input type="submit" name="s1_submit" value="出力">
    </p>
  </div>
{% endblock %}

{% block pagefooter %}
  <script src="/script/oas/transfer_editor.js"></script>
  <script src="/script/oas/calendar.js"></script>
  <script src="/script/oas/pdf_window.js"></script>
{% endblock %}
