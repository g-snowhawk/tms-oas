{% extends "master.tpl" %}

{% block head %}
  <script src="{{ config.global.assets_path }}script/user_alias.js"></script>
{% endblock %}

{% block main %}
  <input type="hidden" name="mode" value="oas.fixedasset.receive:save">
  <input type="hidden" name="id" value="{{ post.id }}">
  {% if post.profile != 1 %}
    <p id="backlink"><a href="?mode=oas.fixedasset.response">一覧に戻る</a></p>
  {% endif %}
  <div class="wrapper">
    <h1>固定資産編集</h1>

    {% if err.vl_item == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_item == 1 %} invalid{% endif %}">
      <label for="item"><sup class="necessary">(必須)</sup>償却方法</label>
      <select name="item" id="item" data-validate="necessary" class="harf">
        <option value="">-- 選択してください --</option>
        {% for item_code,item_name in items %}
        <option value="{{ item_code }}"{% if item_code == post.item %} selected{% endif %}>{{ item_name }}</option>
        {% endfor %}
      </select>
    </div>

    {% if err.vl_title == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_title == 1 %} invalid{% endif %}">
      <label for="title"><sup class="necessary">(必須)</sup>品目</label>
      <input type="text" name="title" id="title" value="{{ post.title }}" data-validate="necessary">
    </div>

    {% if err.vl_quantity == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_quantity == 1 %} invalid{% endif %}">
      <label for="quantity"><sup class="necessary">(必須)</sup>数量</label>
      <input type="text" name="quantity" id="quantity" value="{{ post.quantity }}" data-validate="necessary" class="short">
    </div>

    {% if err.vl_acquire == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_acquire == 1 %} invalid{% endif %}">
      <label for="acquire"><sup class="necessary">(必須)</sup>取得年月日</label>
      <input type="text" name="acquire" id="acquire" value="{{ post.acquire|date('Y-m-d') }}" data-validate="necessary" class="short">
    </div>

    {% if err.vl_price == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_price == 1 %} invalid{% endif %}">
      <label for="price"><sup class="necessary">(必須)</sup>取得額</label>
      <input type="text" name="price" id="price" value="{{ post.price }}" data-validate="necessary" class="short">
    </div>

    {% if err.vl_location == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_location == 1 %} invalid{% endif %}">
      <label for="location"><sup class="necessary">(必須)</sup>所在</label>
      <input type="text" name="location" id="location" value="{{ post.location }}" data-validate="necessary" class="short">
    </div>

    {% if err.vl_durability == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_durability == 1 %} invalid{% endif %}">
      <label for="durability"><sup class="necessary">(必須)</sup>耐用年数</label>
      <input type="text" name="durability" id="durability" value="{{ post.durability }}" data-validate="necessary" class="short">
    </div>

    {% if err.vl_depreciate_type == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_depreciate_type == 1 %} invalid{% endif %}">
      <label for="depreciate_type"><sup class="necessary">(必須)</sup>償却方法</label>
      <select name="depreciate_type" id="depreciate_type" data-validate="necessary" class="short">
        <option value="">-- 選択してください --</option>
        <option value="定額法"{% if post.depreciate_type == "定額法"%} selected{% endif %}>定額法</option>
        <option value="定率法"{% if post.depreciate_type == "定率法"%} selected{% endif %}>定率法</option>
      </select>
    </div>

    {% if err.vl_depreciate_rate == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_depreciate_rate == 1 %} invalid{% endif %}">
      <label for="depreciate_rate"><sup class="necessary">(必須)</sup>償却率</label>
      <input type="text" name="depreciate_rate" id="depreciate_rate" value="{{ post.depreciate_rate }}" data-validate="necessary" class="short">
    </div>

    {% if err.vl_official_ratio == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_official_ratio == 1 %} invalid{% endif %}">
      <label for="official_ratio"><sup class="necessary">(必須)</sup>事業専用割合</label>
      <input type="text" name="official_ratio" id="official_ratio" value="{{ post.official_ratio }}" data-validate="necessary" class="short">
    </div>

    <div class="form-footer">
      <input type="submit" name="s1_submit" value="登録">
    </div>
  </div>
{% endblock %}
