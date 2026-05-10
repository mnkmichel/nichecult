const fs = require('fs')
const { parse } = require('@vue/compiler-sfc')
const original = fs.readFileSync('app/pages/duftkuration.vue', 'utf8')

function test(label, startMarker, endMarker) {
  const src = fs.readFileSync('app/pages/duftkuration.vue', 'utf8')
  const a = src.indexOf(startMarker)
  const b = src.indexOf(endMarker)
  const out = src.slice(0, a) + `<div></div>\n` + src.slice(b)
  const r = parse(out, { filename: 'x.vue' })
  console.log(label, r.errors.length ? 'ERR' : 'OK')
}

test('choice-pair', '<!-- Choice: pair (Mann/Frau) -->', '<!-- Choice: stack (Jahreszeit) -->')
test('choice-stack', '<!-- Choice: stack (Jahreszeit) -->', '<!-- Choice: grid6 (Anlass) -->')
test('choice-grid6', '<!-- Choice: grid6 (Anlass) -->', '<!-- Multi-select (Duftfamilien) -->')
test('multi', '<!-- Multi-select (Duftfamilien) -->', '<!-- Slider -->')
test('slider', '<!-- Slider -->', '<!-- Navigation -->')
