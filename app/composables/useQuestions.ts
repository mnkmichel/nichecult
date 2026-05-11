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
      title: 'Suchen Sie ein Parfum für eine Frau oder einen Mann?',
      options: ['Mann', 'Frau'],
    },
    rating: {
      title: 'Empfinden Sie das Parfum als männlich oder weiblich?',
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
      title: 'Zu welchen Jahreszeiten passt dieses Parfum?',
      options: ['Frühling und Sommer', 'Herbst und Winter', 'Für beide Jahreszeiten'],
    },
  },
  {
    type: 'choice',
    key: 'occasion',
    layout: 'grid6',
    curation: {
      title: 'Zu welchen Anlässen sollte das Parfum am besten passen?',
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
      title: 'Sollte Ihr Parfum eher einen frischen oder warmen Charakter haben?',
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
      title: 'Empfinden Sie das Parfum als natürlich oder synthetisch?',
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
      center: 'Keine Präferenz',
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
      title: 'Sollte Ihr Parfum eher süß oder nicht süß sein?',
      left: 'Gar nicht süß',
      center: 'Keine Präferenz',
      right: 'Sehr süß',
    },
    rating: {
      title: 'Empfinden Sie das Parfum als süß oder nicht süß?',
      left: 'Nicht süß',
      center: 'Neutral',
      right: 'Süß',
    },
  },
  {
    type: 'slider',
    key: 'sexyClean',
    curation: {
      title: 'Sollte das Parfum eher sexy oder clean wirken?',
      left: 'Clean',
      center: 'Keine Präferenz',
      right: 'Sexy',
    },
    rating: {
      title: 'Empfinden Sie das Parfum als sexy oder als clean?',
      left: 'Sexy',
      center: 'Neutral',
      right: 'Clean',
    },
  },
  {
    type: 'multi',
    key: 'duftfamilien',
    curation: {
      title: 'Welche Duftfamilien sagen Ihnen am meisten zu?',
    },
    rating: {
      title: 'Welche Duftfamilien nehmen Sie bei diesem Parfum wahr?',
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
      title: 'Wie gut trifft dieses Parfum Ihren Geschmack?',
      labels: ['Nicht', 'Teilweise', 'Gut', 'Sehr gut', 'Ideal'],
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
