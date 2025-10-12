export const bus = {
  on: (event, cb) => document.addEventListener(event, cb),
  emit: (event, detail={}) => document.dispatchEvent(new CustomEvent(event, { detail }))
};
