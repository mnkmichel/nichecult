const fs = require('fs')
const { parse } = require('@vue/compiler-sfc')
let src = fs.readFileSync('app/pages/duftkuration.vue', 'utf8')
const a = src.indexOf('<!-- Options area -->')
const b = src.indexOf('<!-- Navigation -->')
src = src.slice(0, a)
  + `<!-- Options area -->
          <div class="mt-8 flex flex-1 flex-col justify-center md:mt-10"></div>

          `
  + src.slice(b)
const r = parse(src, { filename: 'x.vue' })
if (r.errors.length) {
  console.log(JSON.stringify(r.errors[0].loc, null, 2))
} else {
  console.log('ok')
}
