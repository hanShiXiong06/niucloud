import request from '@/utils/request'

export type CooperationPosterScene = 'promotion' | 'entity'

// Only the scene crosses the wire. The server derives the signed-in inviter,
// subject code and QR destination; callers cannot supply another member's ID.
export const createCooperationPoster = (scene: CooperationPosterScene) => request.post(
    'hsx_lead/cooperation/poster', { scene }, { showErrorMessage: false }
)
