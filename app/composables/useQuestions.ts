// Einheitliche Fragen für Kuration und Bewertung
export type QuestionContext = 'curation' | 'rating'

type ChoiceQuestion = {
  type: 'choice'
  key: string
  layout: 'pair' | 'stack' | 'grid5' | 'grid6'
  curation: { title: string; options: string[]; optionDescriptions?: string[] }
  rating: { title: string; options: string[]; optionDescriptions?: string[] }
}

type SliderQuestion = {
  type: 'slider'
  key: string
  curation: { title: string; left: string; center: string; right: string }
  rating: { title: string; left: string; center: string; right: string }
}

type MultiQuestion = {
  type: 'multi'
  key: string
  curation: { title: string }
  rating: { title: string }
  options: string[]
}

type LabeledQuestion = {
  type: 'labeled'
  key: string
  curation: { title: string; labels: string[] }
  rating: { title: string; labels: string[] }
}

export type Question = ChoiceQuestion | SliderQuestion | MultiQuestion | LabeledQuestion

export const unifiedQuestions: Question[] = [
  {
    type: 'choice',
    key: 'gender',
    layout: 'pair',
    curation: {
      title: 'Für welches Geschlecht suchen Sie ein Parfum?',
      options: ['Mann', 'Frau'],
    },
    rating: {
      title: 'Welches Geschlecht empfanden Sie beim Parfum?',
      options: ['Männlich', 'Unisex', 'Weiblich'],
    },
  },
  {
    type: 'choice',
    key: 'season',
    layout: 'stack',
    curation: {
      title: 'Zu welcher Jahreszeit soll das Parfum passen?',
      options: ['Frühling und Sommer', 'Herbst und Winter', 'Offen lassen'],
    },
    rating: {
      title: 'Für welche Jahreszeit ist das Parfum geeignet?',
      options: ['Frühling und Sommer', 'Herbst und Winter', 'Für beide Jahreszeiten'],
    },
  },
  {
    type: 'choice',
    key: 'occasion',
    layout: 'grid6',
    curation: {
      title: 'Für welche Anlässe suchen Sie ein Parfum?',
      options: [
        'Alltag & Freizeit',
        'Geschäftliches Umfeld',
        'Date & Nähe',
        'Dinner & besondere Abende',
        'Feiern & Ausgehen',
        'Urlaub & warme Tage',
      ],
      optionDescriptions: [
        'Für ungezwungene Situationen, Stadt, Café, Treffen mit Freunden.',
        'Für Büro, Meetings, professionelle Situationen.',
        'Für intime, persönliche oder romantische Situationen.',
        'Für Restaurant, Bar, elegante Abendpläne.',
        'Für Club, Party, Events oder auffälligere Auftritte.',
        'Für Reisen, Sommer, Outdoor, entspannte Tage.',
      ],
    },
    rating: {
      title: 'Zu welchen Anlässen würden Sie dieses Parfum tragen?',
      options: [
        'Alltag & Freizeit',
        'Geschäftliches Umfeld',
        'Date & Nähe',
        'Dinner & besondere Abende',
        'Feiern & Ausgehen',
        'Urlaub & warme Tage',
      ],
      optionDescriptions: [
        'Für ungezwungene Situationen, Stadt, Café, Treffen mit Freunden.',
        'Für Büro, Meetings, professionelle Situationen.',
        'Für intime, persönliche oder romantische Situationen.',
        'Für Restaurant, Bar, elegante Abendpläne.',
        'Für Club, Party, Events oder auffälligere Auftritte.',
        'Für Reisen, Sommer, Outdoor, entspannte Tage.',
      ],
    },
  },
  {
    type: 'slider',
    key: 'warmFrisch',
    curation: {
      title: 'Sollte Ihr Parfum eher einen frischen oder warmen Character haben?',
      left: 'Warm',
      center: 'Keine Präferenz',
      right: 'Frisch',
    },
    rating: {
      title: 'Empfinden Sie den Duft als warm oder frisch?',
      left: 'Warm',
      center: 'Neutral',
      right: 'Frisch',
    },
  },
  {
    type: 'slider',
    key: 'naturalSynthetisch',
    curation: {
      title: 'Sollte Ihr Parfum einen natürlichen oder synthetischen Charakter haben?',
      left: 'Natürlich',
      center: 'Keine Präferenz',
      right: 'Synthetisch',
    },
    rating: {
      title: 'Natürlich oder Synthetisch?',
      left: 'Natürlich',
      center: 'Neutral',
      right: 'Synthetisch',
    },
  },
  {
    type: 'slider',
    key: 'intensivDezent',
    curation: {
      title: 'Sollte Ihr Parfum eher intensiv oder dezent sein?',
      left: 'Intensiv',
      center: 'Neutral',
      right: 'Dezent',
    },
    rating: {
      title: 'Empfinden Sie diesen Duft als intensiv oder dezent?',
      left: 'Intensiv',
      center: 'Neutral',
      right: 'Dezent',
    },
  },
]

// Zusätzliche Fragen nur für Rating
export const ratingOnlyQuestions: Question[] = [
  {
    type: 'slider',
    key: 'sweetness',
    curation: {
      title: 'Sweetness Preference',
      left: 'Nicht süß',
      center: 'Neutral',
      right: 'Süß',
    },
    rating: {
      title: 'Süß oder Nicht süß?',
      left: 'Nicht süß',
      center: 'Neutral',
      right: 'Süß',
    },
  },
  {
    type: 'slider',
    key: 'sexyClean',
    curation: {
      title: 'Character Preference',
      left: 'Sexy/Sinnlich',
      center: 'Ausgewogen',
      right: 'Clean/Sachlich',
    },
    rating: {
      title: 'Sexy oder Clean?',
      left: 'Sexy/Sinnlich',
      center: 'Ausgewogen',
      right: 'Clean/Sachlich',
    },
  },
  {
    type: 'multi',
    key: 'duftfamilien',
    curation: {
      title: 'Welche Duftfamilien treffen auf dieses Parfum zu?',
    },
    rating: {
      title: 'Welchen Duftfamilien ordnen Sie diesen Duft zu?',
    },
    options: ['Zitrus', 'Fruchtig', 'Blumig', 'Pudrig', 'Aquatisch', 'Holzig', 'Grün', 'Balsamisch', 'Aromatisch', 'Erdig', 'Rauchig', 'Würzig', 'Orientalisch', 'Ledrig', 'Gourmand'],
  },
  {
    type: 'labeled',
    key: 'overallMatch',
    curation: {
      title: 'Overall Match',
      labels: ['Sehr schlecht', 'Schlecht', 'OK', 'Gut', 'Perfekt'],
    },
    rating: {
      title: 'Wie gut passt dieser Duft zu Ihren Vorstellungen?',
      labels: ['Sehr schlecht', 'Schlecht', 'OK', 'Gut', 'Perfekt'],
    },
  },
]

export function getQuestions(context: QuestionContext, includeRatingOnly = false): Question[] {
  const questions = [...unifiedQuestions]
  if (includeRatingOnly) {
    questions.push(...ratingOnlyQuestions)
  }
  return questions
}

export function getQuestionTitle(question: Question, context: QuestionContext): string {
  return question[context].title
}

export function getQuestionOptions(question: Question, context: QuestionContext): string[] {
  if (question.type === 'choice') {
    return question[context].options
  }
  return []
}

export function getQuestionOptionDescriptions(question: Question, context: QuestionContext): string[] {
  if (question.type === 'choice') {
    return question[context].optionDescriptions || []
  }
  return []
}
