export interface HttpAcmeChallenge {
  token: string;
  content: string;
}

export interface HttpAcmeChallengeList {
  domain: string;
  challenges: HttpAcmeChallenge[];
}

export interface HttpAcmeChallengeDetail {
  domain: string;
  token: string;
  content: string;
}

export interface CreateHttpAcmeChallengeRequest {
  token: string;
  content: string;
}
