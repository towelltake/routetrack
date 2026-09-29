import type { JourneyApi } from './index';

declare global {
  interface Window {
    api: JourneyApi;
  }
}
