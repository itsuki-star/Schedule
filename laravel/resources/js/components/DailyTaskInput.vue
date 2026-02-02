<template>
  <div class="wrap">
    <h2>今日の{{ modeLabel }}</h2>

    <div class="row">
      <label>日付</label>
      <input type="date" v-model="date" />
      <button @click="loadMasters">マスタ再読込</button>
    </div>

    <table class="tbl">
      <thead>
        <tr>
          <th>分</th>
          <th>クライアント</th>
          <th>タスク（単体/範囲）</th>
          <th>件数</th>
          <th>メモ</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(r, i) in rows" :key="r.row_uuid">
          <td><input type="number" min="1" v-model.number="r.minutes" style="width:80px" /></td>

          <td>
            <select v-model="r.client_name">
              <option value="">（空白）</option>
              <option v-for="c in clients" :key="c.client_id" :value="c.client_name">
                {{ c.client_name }}
              </option>
            </select>
          </td>

          <td>
            <div style="display:flex; gap:8px; align-items:center;">
              <select v-model="r.task_from">
                <option value="">（選択）</option>
                <option v-for="t in tasks" :key="t.task_key" :value="t.label">
                  {{ t.label }}
                </option>
              </select>
              <span>〜</span>
              <select v-model="r.task_to">
                <option value="">（なし）</option>
                <option v-for="t in tasks" :key="t.task_key + '_to'" :value="t.label">
                  {{ t.label }}
                </option>
              </select>
            </div>
            <div class="hint">
              ※ 範囲が不要なら右側を「なし」にしてください（例：2-3〜2-8 / 源泉所得税）
            </div>
          </td>

          <td><input type="number" min="0" v-model.number="r.count" style="width:80px" /></td>
          <td><input type="text" v-model="r.note" placeholder="自由記述" /></td>

          <td><button @click="removeRow(i)">削除</button></td>
        </tr>
      </tbody>
    </table>

    <div class="actions">
      <button @click="addRow">+ 行追加</button>
      <button class="primary" @click="submit">{{ modeLabel }}を送信</button>
    </div>

    <div v-if="msg" class="msg">{{ msg }}</div>
  </div>
</template>

<script>
export default {
  props: {
    // "plan" or "actual"
    mode: { type: String, default: "plan" },
  },
  data() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, "0");
    const dd = String(today.getDate()).padStart(2, "0");

    return {
      date: `${yyyy}-${mm}-${dd}`,
      tasks: [],
      clients: [],
      rows: [],
      msg: "",
    };
  },
  computed: {
    modeLabel() {
      return this.mode === "actual" ? "実績" : "予定";
    },
  },
  mounted() {
    this.loadMasters();
    this.addRow();
  },
  methods: {
    async loadMasters() {
      this.msg = "";
      const res = await fetch("/api/masters", { credentials: "include" });
      const json = await res.json();
      if (!json.ok) {
        this.msg = "マスタ取得に失敗しました";
        return;
      }
      this.tasks = json.data.tasks;
      this.clients = json.data.clients;
    },
    addRow() {
      this.rows.push({
        row_uuid: crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random(),
        minutes: 60,
        client_name: "",
        task_from: "",
        task_to: "",
        count: "",
        note: "",
      });
    },
    removeRow(i) {
      this.rows.splice(i, 1);
    },
    normalizeRows() {
      // task_label は GAS保存用に作る
      return this.rows.map(r => {
        const taskLabel = r.task_to ? `${r.task_from}〜${r.task_to}` : r.task_from;
        return {
          minutes: r.minutes,
          client_name: r.client_name,
          task_from: r.task_from,
          task_to: r.task_to,
          task_label: taskLabel,
          count: r.count === "" ? null : r.count,
          note: r.note || "",
          row_uuid: r.row_uuid,
        };
      });
    },
    async submit() {
      this.msg = "";

      // 簡易バリデーション
      for (const r of this.rows) {
        if (!r.minutes || r.minutes <= 0) return (this.msg = "分は1以上で入力してください");
        if (!r.task_from) return (this.msg = "タスクを選択してください");
        if (r.task_to && r.task_from === r.task_to) return (this.msg = "範囲の開始と終了が同じです");
      }

      const endpoint = this.mode === "actual" ? "/api/actual" : "/api/plan";
      const res = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "include",
        body: JSON.stringify({
          date: this.date,
          rows: this.normalizeRows(),
        }),
      });

      if (!res.ok) {
        this.msg = "送信に失敗しました（サーバエラー）";
        return;
      }

      const json = await res.json();
      if (!json.ok) {
        this.msg = "送信に失敗しました";
        return;
      }

      this.msg = `${this.modeLabel}を送信しました（${json.data.inserted}件）`;
      // 実績入力のときはそのまま残す、予定入力のときはクリアしたい等は運用で調整
    },
  },
};
</script>

<style scoped>
.wrap { max-width: 1100px; margin: 20px auto; padding: 12px; }
.row { display:flex; gap:10px; align-items:center; margin-bottom: 12px; }
.tbl { width:100%; border-collapse: collapse; }
.tbl th, .tbl td { border:1px solid #ddd; padding:8px; vertical-align: top; }
.actions { margin-top: 12px; display:flex; gap:10px; }
.primary { font-weight: bold; }
.hint { font-size: 12px; color:#666; margin-top: 4px; }
.msg { margin-top: 10px; padding: 10px; background: #f3f3f3; }
</style>
