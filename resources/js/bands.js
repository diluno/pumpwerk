// Weight bands, light to heavy, coloured like competition plates.
// `bg` is the solid plate, `on` its text colour, `pale` the tint for "not on the stack".
// Colours mirror the --color-* tokens in resources/css/app.css.
const BANDS = [
    { upTo: 70, name: 'green', bg: '#15803d', on: '#ffffff', pale: '#ddf0e3' },
    { upTo: 130, name: 'yellow', bg: '#f5b800', on: '#15151b', pale: '#fcf0c4' },
    { upTo: 190, name: 'blue', bg: '#2456e6', on: '#ffffff', pale: '#dfe7fc' },
    { upTo: Infinity, name: 'red', bg: '#d92d20', on: '#ffffff', pale: '#fadfdc' },
];

export const BAND_LIMITS = BANDS.slice(0, -1).map((b) => b.upTo);

export function band(weight) {
    return BANDS.find((b) => weight <= b.upTo + 0.05);
}

export const solid = (weight) => ({ backgroundColor: band(weight).bg, color: band(weight).on });
export const pale = (weight) => ({ backgroundColor: band(weight).pale, color: '#15151b' });
