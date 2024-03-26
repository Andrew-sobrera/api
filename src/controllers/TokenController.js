import jwt from 'jsonwebtoken';

class TokenController {
    async store(email, password){
        
        const token = jwt.sign({ email, password }, process.env.TOKEN_SECRET, {
            expiresIn: process.env.TOKEN_EXPIRATION,
        });        
        
        return token
    }
}

export default new TokenController()