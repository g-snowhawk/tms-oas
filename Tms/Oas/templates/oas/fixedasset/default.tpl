{% extends "master.tpl" %}

{% block main %}
  <input type="hidden" name="mode" value="oas.fixedasset.response:pdf">
  <div class="wrapper narrow-labels">
    <h1>固定資産一覧</h1>
    <table class="item-list">
      <thead>
        <tr>
          <td>番号</td>
          <td>品目</td>
          <td>取得年月日</td>
          <td>所在</td>
          <td>耐用年数</td>
          <td>償却方法</td>
          <td>償却率</td>
          <td>数量</td>
        </tr>
      </thead>
      <tbody>
      {% for item in items %}
        <tr>
          <td class="ta-r"><a href="?mode=oas.fixedasset.response:edit&amp;id={{ item.id }}">{{ item.id }}</a></td>
          <td>{{ item.title }}</td>
          <td class="ta-c">{{ item.acquire|date('Y年m月d日') }}</td>
          <td class="ta-c">{{ item.location }}</td>
          <td class="ta-r">{{ item.durability }}</td>
          <td class="ta-c">{{ item.depreciate_type }}</td>
          <td class="ta-r">{{ item.depreciate_rate }}</td>
          <td class="ta-r">{{ item.quantity }}</td>
        </tr>
      {% endfor %}
      </tbody>
    </table>
    <p><a href="?mode=oas.fixedasset.response:edit" class="button ta-c">新規固定資産</a></p>
    <hr>
    <p>出力する年度を指定してください</p>
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
