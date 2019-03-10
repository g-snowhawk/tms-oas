{% extends "master.tpl" %}

{% block head %}
<script src="/script/oas/pdf_window.js"></script>
{% endblock %}

{% block main %}
  <input type="hidden" name="mode" value="oas.taxation.trialbalance:pdf">
  <div class="wrapper narrow-labels">
    <h1>合計残高試算表作成</h1>
    <p>試算する年度を指定してください</p>
    <div class="fieldset">
      <label>年度</label>
      <input type="text" name="nendo" id="nendo" maxlength="4" class="short" required>
    </div>
    <div class="form-footer">
      <div class="separate-block">
        <span>
          <input type="submit" name="s1_submit" value="作成">
        </span>
      </div>
    </div>
  </div>
{% endblock %}
