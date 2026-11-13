export type SocialUser = {
    email: string;
    name: string;
    avatar: string | null;
};

export type Auth = {
    user: SocialUser | null;
};
