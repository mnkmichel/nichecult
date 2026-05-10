const fs=require('fs')
const {parse}=require('@vue/compiler-sfc')
const src=fs.readFileSync('app/pages/duftkuration.vue','utf8')
const markers=[
  ['pair','<!-- Choice: pair (Mann/Frau) -->','<!-- Choice: stack (Jahreszeit) -->'],
  ['stack','<!-- Choice: stack (Jahreszeit) -->','<!-- Choice: grid6 (Anlass) -->'],
  ['grid6','<!-- Choice: grid6 (Anlass) -->','<!-- Multi-select (Duftfamilien) -->'],
  ['multi','<!-- Multi-select (Duftfamilien) -->','<!-- Slider -->'],
  ['slider','<!-- Slider -->','<!-- Navigation -->'],
]
const optStart=src.indexOf('<!-- Options area -->')
const navStart=src.indexOf('<!-- Navigation -->')
for (const [name,aMark,bMark] of markers){
  const a=src.indexOf(aMark)
  const b=src.indexOf(bMark)
  let block=src.slice(a,b)
  block=block.replace('v-else-if','v-if')
  const testSrc=src.slice(0,optStart)
    + '<!-- Options area -->\n          <div class="mt-8 flex flex-1 flex-col justify-center md:mt-10">\n'
    + block
    + '          </div>\n\n          '
    + src.slice(navStart)
  const r=parse(testSrc,{filename:'x.vue'})
  console.log(name, r.errors.length? 'ERR' : 'OK')
}
