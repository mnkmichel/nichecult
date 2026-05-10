const fs=require('fs')
const src=fs.readFileSync('app/pages/duftkuration.vue','utf8')
const a=src.indexOf('<!-- Options area -->')
const b=src.indexOf('<!-- Navigation -->')
const seg=src.slice(a,b)
const opens=(seg.match(/<div\b(?![^>]*\/>)?/g)||[]).length
const closes=(seg.match(/<\/div>/g)||[]).length
console.log({opens,closes,diff:opens-closes})
